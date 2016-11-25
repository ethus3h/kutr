# kutr [![Build Status](https://travis-ci.org/phanan/koel.svg?branch=master)](https://travis-ci.org/phanan/koel) 
![Showcase](http://koel.phanan.net/dist/img/showcase.png?2)

## Intro

This repository contains a fork of [**Koel**](http://koel.phanan.net) called **Kutr** (pronounced: Cuter).
This will add many experimental features such as:
- ~~Storing all the available tags whenever possible like genre and being able to browse via them (thanks to @alex-phillips)~~ [DONE]
- ~~The capability to browse your music collection by folders too~~ [DONE]
- ~~A smarter tag extraction for invalid files~~ [DONE]
- ~~A GUI that does not errors out  (for example while importing songs with a progress bar, or drag and dropping songs, or selecting songs when column ordering is on)~~ [DONE]
- ~~The ability to install Koel under a CMS (using your CMS' authentication)~~ [DONE]
- ~~The ability to logout directly from your CMS~~ [DONE]
- ~~Per folder "virtual album" (that is, if you save a '.virtual-album' file in a folder with numerous different songs, including "various" artists, "various" albums), they will appear in an album called the same as the folder, under "Various Artists", and will not clutter the album/artist listing with pletora of songs)~~ [DONE] 
- ~~Per user library with sharing capabilities~~ [ABANDONED] 
- An Android player application [STARTED]

I'm trying to keep the changes to phanan's master branch as a minimum so it'll be possible to merge his modifications easily. However, I don't promise I'm not breaking things, but since I'm eating my own dog food, I should be well aware of bug I've induced.
Also, I'm submitting my changes to phanan's repository, but he might decides not to merge them. In that case, they'll stay here for you to use if you need them.

## Install and Upgrade Guide

For system requirements, installation guides, and troubleshooting, head over to [Wiki](https://github.com/phanan/koel/wiki).
All the steps here apply to this fork.
As a side note, you need node 6.x or later (don't use LTS version, it does not work).

If you are upgrading, see [Releases](https://github.com/phanan/koel/releases) for guides corresponding to your version.

## New feature documentation

The `.env.example` contains the new keys required to use the new features. If you already have a `.env` file on your koel installation, just diff them and add the missing keys.

Typically, if you intend to integrate **kutr** in your CMS/web application, you'll have to follow the steps described in `cms_login.php.example` to glue the required calls in **kutr**. 
This file has to be modified to fit your CMS and stored in your CMS/web application (it must be in the same subdomain as your application so the CMS's session is accessible).
Else, you need to change the line that read `const USING_CMS = true;` in resources/assets/js/app.vue to `const USING_CMS = false;`

Also, due to the change required to support genres metadata, it's required to migrate your database schema and re-sync your library:
`php artisan migrate && php artisan koel:sync --force`

**kutr** does not use cookies to store its sessions, but browser's LocalStorage.

## Screenshots
The new features include folder browsing:
![folder](http://i.imgur.com/M08eb1M.png)

Live media library sync update:
![sync](http://i.imgur.com/4R9rfES.png)

Browsing by genres:
![genres](http://i.imgur.com/MRuOA55.png)
![genre](http://i.imgur.com/0VfW3Tx.png)

## Updating

Also, since late update from Koel's master branch, you might encounter some difficulties with updating the code with `composer install`. 
Make sure you have `BROADCAST_DRIVER=null` in your `.env` file

Because I'm rebasing from Koel's master branch, the history for my master branch is changing, and this will likely confuse your git client.
Rebase is easier for me than merging (less work), and produce a cleaner and linear history. 
The drawback is that you'll have to perform `git pull --rebase` to get the new code from my repository (don't do a `git fetch` ;-)

