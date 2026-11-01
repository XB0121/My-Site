import { syncContent } from './sync-content.mjs';
let stopped = false;
process.on('SIGINT', () => { stopped = true; });
process.on('SIGTERM', () => { stopped = true; });
while (!stopped) {
  try { await syncContent({ quiet: true }); }
  catch (error) { console.error(`Content sync: ${error.message}`); }
  await new Promise(resolve => setTimeout(resolve, 4000));
}
