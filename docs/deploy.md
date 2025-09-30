Automatic SCSS -> CSS build and server-side confirmation

Overview

This project compiles SCSS (Dart Sass) to CSS and includes a helper workflow to automatically upload compiled CSS to a server where the server must explicitly accept the changes.

How it works

- Local: `npm run watch-css` watches `public/assets/sass/main.scss` and writes `public/assets/css/main.css`.
- Optional: `npm run watch-and-deploy` watches the compiled CSS and calls `npm run deploy-local` whenever the file changes.
- `scripts/deploy.sh` uploads `public/assets/css/main.css` to a temporary directory on the remote host using `rsync` and then runs a remote accept script (e.g., `/usr/local/bin/remote-accept.sh`) which must be installed by the server operator.

Setup (local)

1. Install dev dependencies:

```sh
npm install
```

2. Build or watch locally:

```sh
npm run build-css
npm run watch-css
```

3. To automatically upload on changes (project must have `onchange` installed):

```sh
# ensure package.json scripts are installed
npm install
# then run
npm run watch-and-deploy
```

Setup (server)

1. Copy `scripts/remote-accept.example.sh` to the server as `/usr/local/bin/remote-accept.sh` and make it executable:

```sh
# on the server (as root or sudo)
cp /path/to/remote-accept.example.sh /usr/local/bin/remote-accept.sh
chmod 755 /usr/local/bin/remote-accept.sh
```

2. Edit the script to add additional validation steps you require (e.g., stylelint, checksum verification, signatures).

3. Ensure the remote user used by `scripts/deploy.sh` has write permission to the `target_dir` and can execute the accept script.

Security notes

- Never put production credentials in repository files. Use environment variables or an SSH agent.
- The server-side accept script must perform validation and run as a trusted account.
- Consider using code signing (GPG) or verifying a pre-shared HMAC to avoid unauthorized updates.

Advanced options

- Use `inotify` or more sophisticated CI/CD pipelines (GitHub Actions, GitLab CI) to validate and deploy changes.
- Use `rsync` filters to deploy only changed files and preserve permissions.
- If you have an SFTP-only host, adapt `deploy.sh` to use `sftp` or `scp` and a remote script to move files.

