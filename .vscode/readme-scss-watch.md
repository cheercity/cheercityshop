This workspace includes a VS Code background task `watch:scss` that runs `npm run watch-css`.

What it does
- Starts `sass --watch public/assets/sass/main.scss:public/assets/css/main.css` in the background when the folder is opened in VS Code.
- Recompiles `public/assets/css/main.css` automatically whenever any imported SCSS file is saved.

How to control it
- To stop the watcher: open the "Terminal > Run Task..." menu and stop the `watch:scss` task, or use the "Terminate Task" button in the Terminal panel.
- To disable auto-start on folder open: edit `.vscode/tasks.json` and remove or change the `"runOn": "folderOpen"` property.

Notes
- The watcher uses the `sass` binary defined in `package.json` (devDependency). Make sure to run `npm install` first.
- If you prefer not to use VS Code tasks, you can run the watcher manually from a terminal:

```sh
npm run watch-css
```
