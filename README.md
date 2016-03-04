# Rapid Web Git Deployment system

The Rapid Web Git Deployment system allows you to deploy a website / web application to your development, testing or live server(s) with a simple `git push` to a specific branch.

This system is still in development. We are aiming for the following deployment features.

* Installation/updating of backend component using `composer` - Done
* Automated running of `gulp` tasks
* Changing of *nix file and directory permissions

# Requirements

The server that you wish to deploy to must meet the following software requirements.

* Any modern Linux distribution (maybe OSX, but this is untested)
* git
* rsync
* PHP (>=5.3)
  * Must be configured to be able to run system commands via `exec()`
