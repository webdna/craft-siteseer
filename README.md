# Siteseer

Visit all the pages, check them, prepare them for others and maybe take a little snapshot or two to remember it by. 

## Requirements

This plugin requires Craft CMS 5.3.0 or later, and PHP 8.2 or later.

## Installation

You can install this plugin from the Plugin Store or with Composer.

#### From the Plugin Store

Go to the Plugin Store in your project’s Control Panel and search for “Siteseer”. Then press “Install”.

#### With Composer

Open your terminal and run the following commands:

```bash
# go to the project directory
cd /path/to/my-project.test

# tell Composer to load the plugin
composer require webdna/craft-siteseer

# tell Craft to install the plugin
./craft plugin/install siteseer
```

## Usage
The plugin is almost exclusively used from its utilities menu. The siteseer utility allows the configuration of a collection destinations, an itinerary of sorts. You can specifiy which sites it should plan to visit, then specify by section, products (requires commerce), category groups too (retro!). You may add any uris you like in the manual URIs table. Static uris can also be added using the config file, for pages without a CMS presence.

Clicking the 'Run a scan' button will then convert your itinerary into a series of queue jobs, which will take place in the background. Once finished you can return to the utilities page to see a summary of any errors found during the trip. From the table you can visit the problem url, find out more about its error code, visit its edit page in the CP (if available) and delete the record once you have addressed the problem.

### Experimental Dev features
The plugin also offers the ability to take html snapshots of the pages that are visited in dev mode only. This hopes to address for developers the difficulty in testing functionality of sites that use static caching on their production hosting. This is not a production worthy static caching solution. 

Use this however you please but our use case was to try and address the difficulty in testing certain functionality on sites that use static caching on their production hosting, which was not trivially replicated locally. You can do a little server rewrite in ddev to serve the static html versions of the pages from an extra subdomain. Something like this:

```nginx
server {
    root /var/www/html/storage/site-seer;
    server_name sscache.XXX.ddev.site;
    ...
    location / {
        absolute_redirect off;
        try_files $uri $uri.html $uri/ =404;
    }
```

and adding the extra hostname to ddev
```yaml
    ...
    additional_hostnames: ["sscache.XXX"]
    ...
```

