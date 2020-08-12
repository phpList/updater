# Automatic updater for phpList

Provides an easy, automated, web-based update mechanism for phpList installations.

### Usage

The new phpList updater gives you an easy way to upgrade your installation via web. In four steps you can update your installation to the latest release.

The Updater is available in phpList 3.3.7+.

### Requirements

The automatic updater requires the following PHP extensions: curl, zip and pdo.

### Technical details

The updater is currently performing the following steps. If one of those steps fail, you will have the possibility to correct the error and retry from the current step.

1. Check if the user is authenticated (Only superadmins can update phpList)
2. Check if there is an update available
3. Check for write permissions
4. Check whether all required phpList files are in place
5. Ask the user if they want a backup of the software:
 - Yes: ask the user for the location
 - If no: continue to the next step.

6. Download new version to a temporary folder
7. Add maintenance mode
8. Replace PHP entry points with "Update in progress" message
9. Delete old files except config, updater and temporary directory
10. Move new files in place
11. Move new PHP entry points in place
12. Move updater to a temporary directory
13. Delete temporary files
14. Remove maintenance mode
15. Move new updater in place
16. Deauthenticate updater session
17. Redirect to the phpList dashboard

### Permissions

The whole phpList directory and the files within it must be writable by the HTTP user under which your web server is running as.
If there is no match between the owner of your phpList files and the user under which your web server is running, you won’t be able to update.
The ownership can be changed in a Linux terminal using this command:

<pre> chown -R user:group /path/to/phpList-directory </pre>

For instance:

<pre> chown -R www-data:www-data /var/www/lists </pre>

Change directory and file permissions:

<pre> find . -type d -exec chmod 755 {} \; </pre>
<pre> find . -type f -exec chmod 644 {} \; </pre>

Permissions vary from host to host. To find the HTTP user check the Apache Server configuration files.
You can view a file's ownership, permissions, and other important information with the ls command, using the -la option:

<pre> ls -la file.php </pre>

The default Apache user and group for some Linux distributions are:

- Debian/Ubuntu: www-data
- Arch Linux: http
- Fedora/CentOS: apache
- openSUSE: user is wwwrun and the group is www

After you change the permissions, you can try again the update.
After a successful update, please consider to re-apply any hardened directory permissions.


### What the updater doesn't do (yet):

The updater is at the moment solely focused on replacing the files of the core installation. It does neither:

- Upgrade the database (this uses the existing database migration code)

### Notes

- The updater stops when it finds unexpected files (not from phpList default installation) and lists them. To continue, you should delete these files or move them outside lists directory.
- It is possible to override the backup checks by reloading the page when the backup check fails. Do not reload the page unless you wish to proceed without a backup in this case.
- When the update process fails you should manually remove actions.txt file inside the config folder in order to reset the process and be able to try again.
- The config directory is required to be writable because the "current step" of the automatic updater is saved inside it.
- The plugins that are now included with phplist will be upgraded as part of the update. Any additional plugins will be kept but not upgraded.

### Future development plans

At the moment only our current stable release, phpList 3, is supported by the updater. We’ll work on adding support to our upcoming phpList 4 release.

### Report bugs and improvements:
https://mantis.phplist.org/set_project.php?project_id=2;185

