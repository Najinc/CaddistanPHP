import { defineConfig, loadEnv, type ConfigEnv, type UserConfig } from "vite";
import tailwindcss from "@tailwindcss/vite";
import process from "node:process";
import { hotFile } from "./hotreload";

export default defineConfig(({ mode }: ConfigEnv): UserConfig => {

    const env = loadEnv(mode, process.cwd(), '')
    const port = Number(env.VITE_PORT) || 5173;
    const domain = new URL(env.APP_URL).hostname;
    const devOrigin = `https://${domain}`;
    
    return {

        server: {
            host: "0.0.0.0",
            port: port,
            strictPort: true,
            origin: devOrigin,
            allowedHosts: [domain],
            
            hmr: {
                protocol: "wss",
                host: domain,
                clientPort: 443,
            },
        },

        build: {
            outDir: "public/dist",
            emptyOutDir: true,
            copyPublicDir: false,
            // Nécessaire pour que PHP puisse lire les fichiers .js/.css
            manifest: true,
            rolldownOptions: {
                input: 'resources/main.ts'
            }
        },

        plugins: [
            hotFile({url: devOrigin }),
            tailwindcss()
        ],

        resolve: {
            alias: {
                "@": "/frontend/js",
            },
        },
    }
});