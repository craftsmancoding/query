# Query


`Query` and its sister Snippet `queryResources` offer a Snippet-interface for xPDO's getCollection method.  This allows you to query 
*any* MODX database collection and format the output, including pagination.  Query can be used
as a replacement for getResources: it offers more flexibility because it's not constrained to fetching only resources.
`queryResources` is dedicated solely to querying on resources, and it helps you navigate the sometimes difficult task 
of filtering on template variables.

<a href="https://www.youtube.com/watch?v=RaUHvJDTkYQ&feature=youtu.be" target="_blank"><img src="http://img.youtube.com/vi/RaUHvJDTkYQ/0.jpg" 
alt="Query Overview" width="480" height="360" border="10" /></a>

## Examples

**Fetch pages matching a certain template:**

    [[!Query? &template=`3`]]

The default class to be searched is `modResource`, i.e. the `modx_site_content` table. So the above Snippet call is 
equivalent to running the following query:

    SELECT * FROM modx_site_content WHERE template='3';

**Find users whose usernames begin with "B" and format the results using a chunk:**

    [[!Query? &_classname=`modUser` &username:STARTS_WITH=`b` &_tpl=`myUser` &_tplOuter=`allUsers`]]

`Query` uses classnames (not tablenames), so you have to be familiar with the xPDO classes.  This Snippet call is 
equivalent to the following SQL statement:

    SELECT * FROM modx_users WHERE username LIKE 'b%';
    

**Paginate all manager events whose names begin with "namespace" and set a URL trigger to listen for $_GET['d'] to trigger
debugging information:**

    [[!Query? &_classname=`modManagerLog` &_limit=`10` &action:STARTS_WITH=`namespace` &_debug=`d:get=0`]]  

In this Snippet call, we use the ":get" input filter to listen for any URL parameters featuring the "d" variable.  You
could trigger the debug output here by appending "&d=1" to your URL, e.g. `http://yoursite.com/some/page?d=1`

**Return JSON data so query can be used to supply an Ajax form:**

    [[!Query? &_classname=`modChunk` &_limit=`10` &_view=`json`]]  

`Query` supports a couple custom views, which go beyond the usual Chunk-based formatting.  Use the `json` view when 
supplying data to an API call or to an Ajax page request that consumed JSON data.

**Quickly set up a search form:** 

Listen for post-data, and join on related tables using the "&_graph" parameter.

    <form action="" method="post">
        Username: <input type="text" name="username" value="[[+query.username]]" /><br /> 
        <input type="submit" value="Search" />
    </form>
    
    [[!Query? 
        &_classname=`modUser` 
        &_graph=`{"Profile":{}}` 
        &_select=`id,username,Profile.email` 
        &username:LIKE=`username:post`
    ]] 

We use the ":post" input filter to grab input for the `&username` parameter.  Note that we use the prefix of "Profile." 
for the email address because of the join (i.e. the graph) from `modUser` to `Profile`.   We also are taking advantage
of the `[[+query.username]]` placeholder.  This isn't a hard-coded placeholder: `Query` sets it because it is one of the
primary Snippet filter attributes (minus its :LIKE modifier).  This is useful for repopulating forms, but be careful:
`Query` does not do thorough sanitization of its variables (it relies on `htmlspecialchars` and MODX's default filters 
only).


**Get a specific list of Chunks:**

    [[!Query? &_classname=`modChunk` &name:IN=`header,footer,meta`]] 

**Return Paginated Results:**

It's critical here that you establish a listener for the offset value that's passed in the URL.  Do this using the 
":get" input filter for the "&_offset" parameter.  Also make sure you include the `[[+pagination_links]]` in your "&_tplOuter" 
or in your page somewhere.

    [[!Query? 
        &_limit=10 
        &_style=`digg` 
        &_tpl=`myTpl` 
        &_tplOuter=`<ul>[[+content]]</ul>[[+pagination_links ]]` 
        &_offset=`offset:get`
    ]]

Where `myTpl` contains the following:

    <li><a href="[[~[[+id]]]]">[[+pagetitle]]</a></li>

WARNING: Nested tags do not seem to parse well as formatting strings, so use Chunks whenever you can. 

> Make sure you include the "pagination_links" placeholder in your page or `_tplOuter` Chunk!




## Installation

You can install Query via the standard MODx package manager.

You can also install Query via Repoman (https://github.com/craftsmancoding/repoman).

1. Clone the Query repository from https://github.com/craftsmancoding/query to a dedicated directory inside your MODx web root, e.g. "mypackages"
2. Run "composer install" on your new repository to pull in the package dependencies.
3. Run the command-line repoman tool on the query/ directory, e.g. "php repoman install /home/myuser/public_html/mypackages/query/"


## Developers

This package was built and is maintained using Repoman (https://github.com/craftsmancoding/repoman).
Any developers who wish to fork the code can install the code during development using the 
Repoman utilities. 


Author: Everett Griffiths <everett@craftsmancoding.com>
Copyright 2014

Official Documentation: https://github.com/craftsmancoding/query/wiki

Bugs and Feature Requests: https://github.com/craftsmancoding/query

