## CAC Database Cleaner

This tool enables you to clean private and potentially sensitive from a WordPress/BuddyPress installation, to prepare the database for sharing with members of a development team. It does the following:

- changes all user passwords to 'password'
- changes all user email addresses to [user_login]@example.com

If you are running Multisite, it does the following:

- deletes all blogs that are marked as spam, deleted, or with blog_public < 0 (see More Privacy Options plugin)
- deletes all posts and pages from all blogs that do not have the post_status 'publish' (this includes revisions)
- deletes all posts and pages from all blogs that have post passwords
- deletes all unpublished comments from all blogs

If you are running BuddyPress, it does the following:

- deletes all non-public xprofile data
- deletes all non-public groups, along with their BuddyPress Group Documents files, their BuddyPress Docs (supports only Docs < 1.2), their bbPress forums (supports only bp-forums legacy), and their activity
- deletes all private messages

## WARNING

This plugin is very dangerous. It has the potential to blow up your website, and possibly your entire neighborhood. **DO NOT USE ON A PRODUCTION SITE**

## Use

- Network activate
- Go to Dashboard > Network Admin > CAC Database Cleaner and click Clean
