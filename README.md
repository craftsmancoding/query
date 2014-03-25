# Query


Query offers a Snippet interface for xPDO's getCollection method.  This allows you to query 
any MODX database collection and format the output, including pagination.  Query can be used
as a replacement for getResources, and it offers much more flexibility because it's not 
constrained to fetching only resources.

## Examples

Fetch pages matching a certain template:

    [[!Query? &template=`3`]]

Find users whose usernames begin with "B" and format the results using a chunk:

    [[!Query? &_classname=`modUser` &username:STARTS_WITH=`b` &_tpl=`myUser` &_tplOuter=`allUsers`]]
    
Paginate all manager events whose names begin with "namespace" and set a URL trigger to listen for $_GET['d'] to trigger
debugging information:

    [[!Query? &_classname=`modManagerLog` &_limit=`10` &action:STARTS_WITH=`namespace` &_debug=`d:get=0`]]  

Return JSON data so query can be used to supply an Ajax form:

    [[!Query? &_classname=`modChunk` &_limit=`10` &_view=`json`]]  


Quickly set up a search form by listening for post-data, and join on related tables:

    <form action="" method="post">
        Username: <input type="text" name="username" value="[[+query.username]]" /><br /> 
        <input type="submit" value="Search" />
    </form>
    
    [[!Query? &_classname=`modUser` &_graph=`{"Profile":{}}` &_select=`id,username,Profile.email` &username:LIKE=`username:post`]] 


Get a specific list of Chunks:

    [[!Query? &_classname=`modChunk` &name:IN=`header,footer,meta`]] 


## Installation

You can install Query via the standard MODx package manager.

You can also install Query via Repoman (https://github.com/craftsmancoding/repoman).

1. Clone the Query repository from https://github.com/craftsmancoding/query to a dedicated directory inside your MODx web root, e.g. "mypackages"
2. Run "composer install" on your new repository to pull in the package dependencies.
3. Run the command-line repoman tool on the query/ directory, e.g. "php repoman install /home/myuser/public_html/mypackages/query/"


## Developers

This package was built and is maintained using Repoman (https://github.com/craftsmancoding/repoman).
Any developers who wish to fork the code can install the code during development using the 
Repoman utilities.  This makes it much easier to manage your repositories.


Author: Everett Griffiths <everett@craftsmancoding.com>
Copyright 2014

Official Documentation: https://github.com/craftsmancoding/query/wiki

Bugs and Feature Requests: https://github.com/craftsmancoding/query

