# Automatic updater for phpList

Provides an easy, automated, web-based update mechanism for phpList installations.

### Usage

The new phpList updater gives you an easy way to upgrade your installation via web. In just four steps you can update your installation to the latest release. 

The updater will be available in all releases starting with RC 3.3.7 

### Technical details

The updater is currently performing the following steps. If one of those steps fail, you will have the possibility to correct the error and retry from the current step.

1. Check if the user is authenticated (Only superadmins can update phpList)
2. Check if there is an update available
3. Check for write permissions
4. Check whether all required phpList files are in place
5. Ask the user if they want a backup of the software:
 - Yes:ask the user for the location
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
16. Deauthenticate updater session.
17. Redirect to the phpList dashboard

### What the updater doesn't do (yet):

The updater is at the moment solely focused on replacing the files of the core installation. It does neither:

Upgrade the database (this will use the existing database migration code)
Upgrade the plugins (this will use the existing plugin updater)


### Future development plans

At the moment only our current stable release, phpList 3, is supported by the updater. We’ll work on adding support to our upcoming phpList 4 release.

### Report bugs and improvements: 
https://mantis.phplist.org/set_project.php?project_id=2;185

