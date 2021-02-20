@echo off

set MSG=%1

if '%MSG%'=='' (
	set MSG="fix"
)

git add .
git commit -m %MSG%
git push