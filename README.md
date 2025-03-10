# Deploy Yourself

A script to update a kirby website code (not content) by pulling from git.

Camouflaged as plugin to look familiar, but actually called directly.

Useful when deployment of changes of a kirby website via FTP or RSYNC or direct shell access to the server is not feasibly, but the server allows you to use GIT via PHP `exec()`. This can be handy in restricted shared hosting environments.

## Prerequisites
- You store your kirby website in a git folder and you want the contents of that git on your webserver.
  - you are aware that this will only pull files from git. Running composer or npm is out of scope for this kind of deployment. 
  - If you want to deploy your `vendor` folder this way, you have to add it to your git repository. When you do this, and having `vendor` in your git and not in `.gitignore` is new to you: assume it is a larger `composer.lock` that defines exactly what will be deployed to your webserver. "Easier deployment in restricted environments" comes with "more files in git". Make sure to only add subfolders of `vendor` you need for production, those generated by `composer install --no-dev --optimize-autoloader`. 
  - You could create a pipeline that creates a special release branch and pull from that, but this is out of scope.
  - You followed the instructions of using kirby and added to `.gitignore`: `content` and certain `site` subfolders.
- Your web-server already has a .git folder with a valid .git/config and calling `git pull` is all you need after you pushed changes to your repository. 
  - On restricted servers with no shell access, using https:// as protocol for `[remote "origin"] url` and adding username and token into the URL is legit. 
  - _Note: If you want this plugin to also do the initial checkout for you, create a ticket and be ready to help out._
- Your git server supports webhooks. Both github and gitlab do support webhooks.


## Installation

Install it via composer:
`composer require leobard/deploy-yourself`

Copy the plugin into your plugins folder.

Copy `deploy-yourself-hook.php` to the main/root folder of your webserver where you also placed the kirby .htaccess and index.php. Adapt the paths there if necessary.

This will add deploy-yourself independent of Kirby to a route that is handled by deploy-yourself itself:
`https://example.com/deploy-yourself-hook.php`.

Updating Kirby from a route outside of kirby seems better than from within a running Kirby process.

As default, this plugin will also register the same functionality as Kirby hook:
`https://example.com/deploy-yourself/hook`.

It is recommended to hide the plugin's Kirby Panel page from non-admin users. Add the following to all non-admin user blueprints, for example `site/blueprints/users/default.yml`. Once [Panel area only for admins](https://forum.getkirby.com/t/panel-area-only-for-admins/32707/5) has a better answer, this step may not be necessary anymore.

```
...
permissions:
  access:
    deployyourself: false
```

## Configuration

Use key `leobard.deploy-yourself`, see documentation in Class src/DeployYourself.php, function config().

## Calling the hook

Register the hook `https://example.com/deploy-yourself-hook.php` as git webhook on your git server/repository.

See configuration for a HTTP header token to secure the hook.

The hook will...
1. run `git fetch` 
2. put the site in maintenance mode
3. If the URL param `?reset=hard` is added to the hook the hook will run `git reset --hard`
4. run `git merge` (completing the `git pull`) 
5. optionally, post_pull_cmds
6. undo maintenance mode
... while logging these steps.

## Logging

As webhooks are hard to trace, and this plugin is self-reliant and minimal, it comes with its own PSR3 inspired mini-logging implementation. By default it will log on level 'notice' and will write commands and their results into `site/logs/deploy-yourself-{ISODATETIME}.log`. It will retain the last 10 logs. This is configurable.

## Dry-run

There is a dryrun option which will not run commands but only log them.

## Custom commands to run after pull
After the initial pull, you can run commands on the server using `exec()`. Configure them as array in `post_pull_cmds`.

## Panel area to view log messages
Log files are listed in a new area "Deploy yourself" in the Kirby panel menu.

The URL path is `/admin/deploy-yourself/index` .

It is only visible for admin users.

## Support and development

This comes AS-IS with no support on a "works for me, Leo Sauermann, no need to add anything" attitiude. 

If this also "works for you" and you want to improve it, please add issues and pull requests to the github repo. If your additions are useful extensions from the initial state, and your code looks fine in my eyes, we may want to continue together as co-maintainers.

