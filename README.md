# WooCommerce Dynamic Pricing & Discounts (plugin)

This repository contains the `woo-dynamic-pricing-discounts` WordPress plugin. It provides a rule-based dynamic pricing and discount engine for WooCommerce.

Contents
- `woo-dynamic-pricing-discounts.php` — plugin bootstrap
- `includes/` — core classes and helpers
- `templates/` — public markup templates
- `assets/` — CSS/JS/images
- `tests/` — PHPUnit tests scaffold

Local git & GitHub (recommended)

1. Initialize local git repo (I'll do this step for you):

   cd "/Users/mair13/Local Sites/blueprintv3/app/public/wp-content/plugins/woo-dynamic-pricing-discounts"
   git status

2. Create a private GitHub repository and push (choose one):

- Using GitHub CLI (`gh`):

   gh repo create <OWNER>/<REPO_NAME> --private --source=. --remote=origin --push

- Or create via the GitHub web UI: https://github.com/new (select "Private") and then run:

   git remote add origin git@github.com:<OWNER>/<REPO_NAME>.git
   git branch -M main
   git push -u origin main

Notes
- `.gitignore` already contains common WordPress, PHP, and editor exclusions.
- If you'd like, I can attempt to run `gh repo create` for you, but I'll need your permission and the `gh` CLI installed and authenticated in this environment.

License
-------
This project is licensed under the MIT License — see `LICENSE` for details.

Next steps I can take if you want:
- Run `git init` and make an initial commit locally (will do now unless you object).
- Attempt to create the private GitHub repo using `gh` (requires your permission).
- Push the local repo to the newly created GitHub remote.
