Manual deploy instructions

This repository contains a SFTP/FTPS config for VS Code in `.vscode/sftp.json`. For safety we disabled `uploadOnSave` to avoid accidental uploads.

Manual upload options

1) Use lftp (recommended):

- Ensure lftp is installed (macOS: `brew install lftp`).
- Run `./scripts/upload_ftps.sh --dry-run` to preview the command (it will not upload).
- Run `./scripts/upload_ftps.sh` to perform the upload. The script will prompt for the remote password.

2) Use VS Code SFTP extension manually:

- Open the SFTP pane and use the "Upload" or "Sync Local -> Remote" command. Make sure `uploadOnSave` is false.

Security notes

- Do not commit passwords or `.env.local` with secrets.
- If you want me to run the upload now, explicitly say so and confirm you accept using the credentials from `.vscode/sftp.json`. I will not run uploads without explicit consent.
