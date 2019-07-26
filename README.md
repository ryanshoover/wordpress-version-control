# WordPress Version Control

This Composer plugin helps to keep the version of your WordPress packages in sync. It will update the version number in all the files that track the version.

* composer.json version field
* package.json version field
* Plugin primary file
* Theme style.css file
* Theme functions.php file
* Git tag

## Installation

### Recommended: Global installation

Install the plugin globally so that you can use it on all your projects.

```bash
$ composer global require ryanshoover/wp-version-control

Changed current directory to /Users/ryan.hoover/.composer
./composer.json has been updated
Loading composer repositories with package information
Updating dependencies (including require-dev)
Package operations: 2 installs, 0 updates, 0 removals
  - Installing hassankhan/config
  - Installing ryanshoover/wp-version-control
Writing lock file
Generating autoload files
```

### Nuanced: Project-specific installation

Add the plugin to your project's composer.json to enable it for all project collaborators.

```bash
$ composer require ryanshoover/wp-version-control

./composer.json has been updated
Loading composer repositories with package information
Updating dependencies (including require-dev)
Package operations: 2 installs, 0 updates, 0 removals
  - Installing hassankhan/config
  - Installing ryanshoover/wp-version-control
Writing lock file
Generating autoload files
```

## Usage

Once you've commited all changes to your master branch, run the release command from your local terminal. The command will update all your version files, commit those changes, create a new tag with the new version, and push the tag to your git origin remote.

Documentation

```bash
Usage:
  release [options] [--] <segment>

Arguments:
  segment                        Release level: major|minor|patch

Options:
    --message[=MESSAGE]          Git commit and tag message
```

Example

```bash
$ composer release major
Git commit and tag message: Creating first major release
📣 Updating version from 0.12.5 to 1.0.0

Processing composer.json

package.json not found

style.css not found

src/sass/style.scss not found

plugin.php not found

🎁 Releasing new git tag
```
