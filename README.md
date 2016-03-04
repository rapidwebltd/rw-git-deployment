# Rapid Web Git Deployment system

The Rapid Web Git Deployment system allows you to deploy a website / web application to your development, testing or live server(s) with a simple `git push` to a specific branch.

## Features

* Integration with GitHub and GitLab push webhooks
* Dependency installation is done in a temporary location before
syncronising with the deployment directory
* Installation/updating of backend components using `composer`
* Automated running of `gulp` tasks
* Changing of *nix file and directory permissions

## Requirements

The server that you wish to deploy to must meet the following software requirements.

* Any modern Linux distribution (maybe OSX, but this is untested)
* git
* rsync
* PHP (>=5.3)
  * Must be configured to be able to run system commands via `exec()`

### Optional extras

If the project you wish to deploy makes use of any of the following, you will need to ensure this software is setup server-side.

* composer
* npm
* gulp
