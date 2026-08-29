# BioTracker — agent instructions

**Orient before changing anything.** Read [docs/HANDOVER.md](docs/HANDOVER.md) first; it indexes
this project's docs ([docs/PRD.md](docs/PRD.md), [docs/DATA-MODEL.md](docs/DATA-MODEL.md),
[docs/DECISIONS.md](docs/DECISIONS.md)). Restate the goal, success criteria, and data shape before
proposing changes. Do not act on a partial read. This follows the global project documentation
standard.

## Conventions
- PHP is Laravel Herd's and is **not on PATH**: `C:\Users\r\.config\herd\bin\php84\php.exe`.
- Tests: `.\vendor\bin\phpunit.bat`, or `php artisan test`. The suite is PHPUnit — there is no
  Pest binary in `vendor/bin`.
- Style: `.\vendor\bin\pint.bat --test <the files you touched>`. A bare run rewrites about 66
  unrelated PHP files, because the repo has never been Pint-formatted. Reformatting it all is
  Rob's call and belongs in its own commit.
- Frontend: `npm run build`. `composer run dev` runs Laravel, Vite, the queue and logs together.
- Work lives on the board in `docs/board/`: one card per file, and the folder it sits in is its
  state. See [docs/board/README.md](docs/board/README.md).
