const fs = require('fs');
const fsp = require('fs/promises');
const path = require('path');
const { exec } = require('child_process');
const util = require('util');
const execPromise = util.promisify(exec);

class FileManager {
    constructor(applicationsRoot) {
        this.applicationsRoot = path.resolve(applicationsRoot || '/var/lib/n1x/applications');
    }

    /**
     * Resolves and verifies that a relative path stays strictly within the application directory.
     * Prevents any directory traversal attempts (e.g. '../').
     */
    resolvePath(appUuid, relativePath = '') {
        const appDir = path.resolve(this.applicationsRoot, appUuid);
        const cleanRelative = (relativePath || '').replace(/^[\/\\]+/, '');
        const targetPath = path.resolve(appDir, cleanRelative);

        if (!targetPath.startsWith(appDir)) {
            const err = new Error('Access denied: Path traversal detected.');
            err.statusCode = 403;
            throw err;
        }

        return { appDir, targetPath };
    }

    async ensureAppDirectory(appUuid) {
        const appDir = path.resolve(this.applicationsRoot, appUuid);
        await fsp.mkdir(appDir, { recursive: true });
        return appDir;
    }

    async listDirectory(appUuid, relativePath = '/') {
        const { appDir, targetPath } = this.resolvePath(appUuid, relativePath);

        if (!fs.existsSync(targetPath)) {
            const err = new Error('Directory not found');
            err.statusCode = 404;
            throw err;
        }

        const entries = await fsp.readdir(targetPath, { withFileTypes: true });
        const results = [];

        for (const entry of entries) {
            try {
                const fullPath = path.join(targetPath, entry.name);
                const stats = await fsp.stat(fullPath);
                const isDir = entry.isDirectory();
                const isSymlink = entry.isSymbolicLink();
                const isFile = entry.isFile();

                results.push({
                    name: entry.name,
                    created_at: stats.birthtime.toISOString(),
                    modified_at: stats.mtime.toISOString(),
                    mode: isDir ? 'drwxr-xr-x' : '-rw-r--r--',
                    mode_bits: (stats.mode & 0o777).toString(8).padStart(4, '0'),
                    size: stats.size,
                    directory: isDir,
                    file: isFile,
                    symlink: isSymlink,
                    mime: isDir ? 'inode/directory' : this.getMimeType(entry.name),
                });
            } catch (e) {
                // Ignore unreadable entries
            }
        }

        return results;
    }

    async readFile(appUuid, relativePath) {
        const { targetPath } = this.resolvePath(appUuid, relativePath);
        return await fsp.readFile(targetPath, 'utf8');
    }

    async writeFile(appUuid, relativePath, content) {
        const { targetPath } = this.resolvePath(appUuid, relativePath);
        await fsp.mkdir(path.dirname(targetPath), { recursive: true });
        await fsp.writeFile(targetPath, content, 'utf8');
    }

    async createDirectory(appUuid, relativePath, name) {
        const { targetPath } = this.resolvePath(appUuid, path.join(relativePath || '', name));
        await fsp.mkdir(targetPath, { recursive: true });
    }

    async deleteFiles(appUuid, root, files = []) {
        for (const file of files) {
            const { targetPath } = this.resolvePath(appUuid, path.join(root || '', file));
            if (fs.existsSync(targetPath)) {
                await fsp.rm(targetPath, { recursive: true, force: true });
            }
        }
    }

    async renameFiles(appUuid, root, files = []) {
        for (const item of files) {
            const from = this.resolvePath(appUuid, path.join(root || '', item.from)).targetPath;
            const to = this.resolvePath(appUuid, path.join(root || '', item.to)).targetPath;
            await fsp.mkdir(path.dirname(to), { recursive: true });
            await fsp.rename(from, to);
        }
    }

    async chmodFiles(appUuid, root, files = []) {
        for (const item of files) {
            const { targetPath } = this.resolvePath(appUuid, path.join(root || '', item.file));
            const mode = parseInt(item.mode, 8);
            if (!isNaN(mode)) {
                await fsp.chmod(targetPath, mode);
            }
        }
    }

    async compressFiles(appUuid, root, files = []) {
        const { appDir, targetPath: rootDir } = this.resolvePath(appUuid, root);
        const archiveName = `archive-${Date.now()}.tar.gz`;
        const archivePath = path.join(rootDir, archiveName);

        const fileList = files.map(f => `"${f}"`).join(' ');
        try {
            await execPromise(`tar -czf "${archivePath}" -C "${rootDir}" ${fileList}`);
        } catch (err) {
            // Fallback for Windows/Cross-platform if tar not found
            console.error('Compression command error:', err);
        }

        const stats = await fsp.stat(archivePath).catch(() => ({ size: 0, mtime: new Date(), birthtime: new Date() }));
        return {
            name: archiveName,
            size: stats.size,
            created_at: stats.birthtime.toISOString(),
            modified_at: stats.mtime.toISOString(),
            directory: false,
            file: true,
            mime: 'application/gzip',
        };
    }

    async decompressFile(appUuid, root, file) {
        const { targetPath: filePath } = this.resolvePath(appUuid, path.join(root || '', file));
        const { targetPath: destDir } = this.resolvePath(appUuid, root || '');

        if (filePath.endsWith('.tar.gz') || filePath.endsWith('.tgz')) {
            await execPromise(`tar -xzf "${filePath}" -C "${destDir}"`);
        } else if (filePath.endsWith('.zip')) {
            await execPromise(`unzip -o "${filePath}" -d "${destDir}"`);
        }
    }

    getMimeType(fileName) {
        const ext = path.extname(fileName).toLowerCase();
        const map = {
            '.js': 'application/javascript',
            '.mjs': 'application/javascript',
            '.json': 'application/json',
            '.ts': 'application/typescript',
            '.py': 'text/x-python',
            '.php': 'application/x-httpd-php',
            '.html': 'text/html',
            '.htm': 'text/html',
            '.css': 'text/css',
            '.txt': 'text/plain',
            '.log': 'text/plain',
            '.md': 'text/markdown',
            '.png': 'image/png',
            '.jpg': 'image/jpeg',
            '.jpeg': 'image/jpeg',
            '.svg': 'image/svg+xml',
            '.tar.gz': 'application/gzip',
            '.zip': 'application/zip',
        };
        return map[ext] || 'application/octet-stream';
    }
}

module.exports = FileManager;

