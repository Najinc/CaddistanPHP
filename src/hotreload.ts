/** Plugin Vite pour générer le fichier hot */

import fs from 'node:fs';
import path from 'node:path';
import type { Plugin } from 'vite';

export interface HotFileOptions {
    url: string;
    file?: string;
}

const SIGNALS_REGISTERED = Symbol.for('php-hot-file:signals');

export function hotFile({ url, file = 'public/hot' }: HotFileOptions): Plugin {
    let hotFilePath = '';

    const write = (): void => {
        fs.mkdirSync(path.dirname(hotFilePath), { recursive: true });
        fs.writeFileSync(hotFilePath, url);
    };

    const clean = (): void => {
        if (hotFilePath && fs.existsSync(hotFilePath)) {
            fs.rmSync(hotFilePath);
        }
    };

    return {
        name: 'php-hot-file',
        apply: 'serve',

        configResolved(config) {
            hotFilePath = path.resolve(config.root, file);
        },

        configureServer(server) {
            server.httpServer?.once('listening', write);
            server.httpServer?.once('close', clean);

            const globals = globalThis as Record<symbol, boolean>;
            if (!globals[SIGNALS_REGISTERED]) {
                globals[SIGNALS_REGISTERED] = true;
                process.on('exit', clean);
                for (const signal of ['SIGINT', 'SIGTERM', 'SIGHUP'] as const) {
                    process.on(signal, () => process.exit());
                }
            }
        },
    };
}