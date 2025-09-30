FileMaker integration — quick start

Set these environment variables (e.g. in .env.local):

FM_HOST=https://your-fm-host/fmi/data/vLatest
FM_DB=YourFileMakerDB
FM_USER=api_user
FM_PASS=secret

Available debug endpoints (local dev server):
- POST /api/fm/auth             -> create/check token
- POST /api/fm/{layout}/find    -> find records (POST _find)
- POST /api/fm/{layout}         -> create record
- PATCH /api/fm/{layout}/{id}   -> edit record
- DELETE /api/fm/{layout}/{id}  -> delete record

Useful debug pages (browser):
- /debug/banner                 -> Banner debug UI
- /debug/module                 -> Module debug UI
- /debug/products               -> Products debug UI
- /debug/users/test             -> Minimal users connectivity test

If you need to run a quick one-off test without Symfony, use the scripts in public/ (they expect vendor/ to exist). Ensure you run `composer install` first.
