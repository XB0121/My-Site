import { existsSync } from 'node:fs';
console.log(`Node ${process.version}`);
for (const file of ['node_modules/astro', 'node_modules/@wp-playground/cli', 'src/data/posts.json']) {
  console.log(`${existsSync(file) ? 'OK' : 'MISSING'} ${file}`);
}
try {
  const r = await fetch('http://127.0.0.1:9400/?rest_route=/wp/v2/posts', { signal: AbortSignal.timeout(5000) });
  if (!r.ok || !Array.isArray(await r.json())) throw Error(`HTTP ${r.status}`);
  console.log('OK WordPress REST API');
} catch (error) {
  console.error(`WordPress unavailable: ${error.message}. Start it with npm run wp.`);
  process.exitCode = 1;
}
