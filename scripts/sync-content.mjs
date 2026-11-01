import { createHash } from 'node:crypto';
import { mkdir, open, readFile, rename, unlink, writeFile } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';
const origin = (process.env.WORDPRESS_URL || 'http://127.0.0.1:9400').replace(/\/$/, '');
const api = async (route, params = {}) => {
  const url = new URL(origin);
  url.searchParams.set('rest_route', route);
  for (const [key, value] of Object.entries(params)) url.searchParams.set(key, String(value));
  const response = await fetch(url, { signal: AbortSignal.timeout(30000), cache: 'no-store' });
  if (!response.ok) throw new Error(`WordPress API ${route}: ${response.status} ${response.statusText}`);
  return { data: await response.json(), headers: response.headers };
};
const imageTypes = new Map([['image/jpeg', 'jpg'], ['image/png', 'png'], ['image/webp', 'webp'], ['image/gif', 'gif'], ['image/avif', 'avif']]);
const mirrored = new Map();
async function mirror(url) {
  if (!url || new URL(url, origin).origin !== new URL(origin).origin) return url;
  if (mirrored.has(url)) return mirrored.get(url);
  const response = await fetch(new URL(url, origin), { signal: AbortSignal.timeout(30000) });
  if (!response.ok) throw new Error(`Unable to download WordPress image: HTTP ${response.status}`);
  const extension = imageTypes.get(response.headers.get('content-type')?.split(';')[0]);
  if (!extension) throw new Error('Unsupported image. Use JPG, PNG, WebP, GIF or AVIF.');
  if (Number(response.headers.get('content-length')) > 15 * 1024 * 1024) throw new Error('Image exceeds 15 MB.');
  const bytes = Buffer.from(await response.arrayBuffer());
  if (bytes.length > 15 * 1024 * 1024) throw new Error('Image exceeds 15 MB.');
  const name = `wp-${createHash('sha256').update(bytes).digest('hex').slice(0, 20)}.${extension}`;
  await mkdir('public/media', { recursive: true });
  let existing;
  try { existing = await readFile(`public/media/${name}`); } catch {}
  if (!existing?.equals(bytes)) await writeFile(`public/media/${name}`, bytes);
  const local = `media/${name}`;
  mirrored.set(url, local);
  return local;
}
async function mirrorImage(image) { return image?.url ? { ...image, url: await mirror(image.url) } : null; }
async function mirrorHtml(html) {
  let result = html || '';
  for (const match of [...result.matchAll(/<img\b[^>]*\bsrc=["']([^"']+)["'][^>]*>/gi)]) {
    const local = await mirror(match[1]);
    if (local === match[1]) continue;
    const tag = match[0].replace(match[1], `__SITE_BASE__${local}`).replace(/\s(?:srcset|sizes)=["'][^"']*["']/gi, '');
    result = result.replace(match[0], tag);
  }
  return result;
}
async function writeChanged(path, value) {
  const next = JSON.stringify(value, null, 2) + '\n';
  try { if (await readFile(path, 'utf8') === next) return; } catch {}
  await writeFile(`${path}.tmp`, next);
  await rename(`${path}.tmp`, path);
}
async function syncUnlocked({ quiet = false } = {}) {
  mirrored.clear();
  const { data: site } = await api('/vibe-site/v1/content');
  if (!site.settings || !Array.isArray(site.projects)) throw new Error('Install the My Site WordPress plugin before syncing.');
  const posts = [];
  let pages = 1;
  for (let page = 1; page <= pages; page++) {
    const { data: batch, headers } = await api('/wp/v2/posts', { per_page: 100, page });
    if (!Array.isArray(batch)) throw new Error('WordPress did not return an article list.');
    pages = Number(headers.get('x-wp-totalpages') || 1);
    for (const post of batch) posts.push({ id: post.id, title: post.title.rendered, content: await mirrorHtml(post.content.rendered), excerpt: await mirrorHtml(post.excerpt.rendered), date: post.date });
  }
  site.settings.hero_image = await mirrorImage(site.settings.hero_image);
  site.settings.about_image = await mirrorImage(site.settings.about_image);
  for (const project of site.projects) {
    project.image = await mirrorImage(project.image);
    project.content = await mirrorHtml(project.content);
  }
  await mkdir('src/data', { recursive: true });
  await writeChanged('src/data/site.json', site);
  await writeChanged('src/data/posts.json', posts);
  if (!quiet) console.log(`Synced website settings, ${site.projects.length} projects and ${posts.length} articles.`);
}
export async function syncContent(options = {}) {
  await mkdir('.local', { recursive: true });
  const lockPath = '.local/content-sync.lock';
  let lock;
  for (let attempt = 0; attempt < 300; attempt++) {
    try { lock = await open(lockPath, 'wx'); await lock.writeFile(String(process.pid)); break; }
    catch (error) {
      if (error.code !== 'EEXIST') throw error;
      let owner;
      try { owner = Number(await readFile(lockPath, 'utf8')); } catch {}
      if (owner) {
        try { process.kill(owner, 0); }
        catch (checkError) { if (checkError.code === 'ESRCH') { try { await unlink(lockPath); } catch {} } }
      }
      await new Promise(resolve => setTimeout(resolve, 200));
    }
  }
  if (!lock) throw new Error('Another content sync is still running. Try again shortly.');
  try { return await syncUnlocked(options); }
  finally { await lock.close(); await unlink(lockPath); }
}
if (process.argv[1] && import.meta.url === pathToFileURL(process.argv[1]).href) await syncContent();
