import { mkdir, writeFile } from 'node:fs/promises';

const origin = (process.env.WORDPRESS_URL || 'http://127.0.0.1:9400').replace(/\/$/, '');
const posts = [];
let pages = 1;
for (let page = 1; page <= pages; page++) {
  const url = new URL(origin);
  // Works with both plain and pretty WordPress permalinks.
  url.searchParams.set('rest_route', '/wp/v2/posts');
  url.searchParams.set('per_page', '100');
  url.searchParams.set('page', String(page));
  const response = await fetch(url, { signal: AbortSignal.timeout(30000) });
  if (!response.ok) throw new Error(`WordPress API: ${response.status} ${response.statusText}`);
  const batch = await response.json();
  if (!Array.isArray(batch)) throw new Error('WordPress did not return an article list.');
  pages = Number(response.headers.get('x-wp-totalpages') || 1);
  posts.push(...batch.map(post => ({
    id: post.id,
    title: post.title.rendered,
    content: post.content.rendered,
    excerpt: post.excerpt.rendered,
    date: post.date,
  })));
}
await mkdir('src/data', { recursive: true });
await writeFile('src/data/posts.json', JSON.stringify(posts, null, 2) + '\n');
console.log(`Synced ${posts.length} published WordPress articles.`);
