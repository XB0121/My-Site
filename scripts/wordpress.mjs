import { existsSync, mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import { runCLI } from '@wp-playground/cli';

// Store WordPress, SQLite and uploads inside this project.
const storage = resolve('.local');
mkdirSync(storage, { recursive: true });
mkdirSync(resolve('.local/wordpress'), { recursive: true });
await runCLI({
  command: 'server',
  port: 9400,
  'site-url': 'http://127.0.0.1:9400',
  'mount-before-install': [{ hostPath: resolve('.local/wordpress'), vfsPath: '/wordpress' }],
  wordpressInstallMode: existsSync(resolve('.local/wordpress/wp-load.php'))
    ? 'do-not-attempt-installing' : 'download-and-install',
  login: false,
});
