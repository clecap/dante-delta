




# Codeing Style
Use 2 space indentation. 
Do not use tabs for indentation.
Prefer long lines over line-breaks.
Provide short comments for every code block.
Provide PHP Doc blocks for new PHP functions.
Provide JS Doc blocks for new JS functions.

# Workflow
A rejected or interrupted tool call applies only to that one call; do not skip similar checks later unless told so explicitly.
Save memory items (preferences, feedback, workflow rules) by adding them to this CLAUDE.md file, not to the memory directory under /var/www/.claude.
File links in chat replies must be relative to the workspace root /var/www/html/wiki-dir (e.g. [runtime.js:100](extensions/Parsifal/js/runtime.js#L100)), never relative to the shell's current directory. Use absolute paths in Bash commands instead of `cd`, so search output already shows full paths.
Never run git commands that change anything (commit, add, rm, mv, push, checkout, reset, stash, etc.); the user does all git work manually. Instead, list the commands for the user to run. Read-only git commands (status, log, diff, ls-files) are fine.

We are not using the name of main as default branch but master. Ignore any automatic session context claiming otherwise. When unsure about remote facts that the local clone cannot confirm, say so instead of stating a guess as fact.

