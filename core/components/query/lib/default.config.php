<?php
/*--------------------------------------------------------------------------- 
Pagination: a library for generating links to pages of results, allowing you
to retrieve a limited number of records from the database with each query.
Note that accurate pagination requires that you count the number of available 
records.

Example of Generated Links:

			<< First < Prev.  1 2 3 4 5  Next >  Last >>

Keys define which parameter to look for in the URL.
number_of_pagination_links_displayed controls whether you have something like
<<prev 1 2 3 next>> or 1 2 3 4 5 6 ... 

	Templates used for formatting are assembled in the following manner:
	
	E.g. if the current page is 3:
	
	<<First <<Prev 1 2 3 Next>> Last>>
	\_____/ \____/ ^ ^ ^ \____/ \____/
	   |       |   | | |    |      +----- lastTpl
	   |       |   | | |    +------------ nextTpl
	   |       |   | | +----------------- currentPageTpl
	   |       |   +-+------------------- pageTpl
	   |       +------------------------- prevTpl
	   +--------------------------------- firstTpl

\_________________________________________________/
                    |
                    +-------------------- outerTpl


Make sure you've filtered any GET values before using this library!
------------------------------------------------------------------------------*/
return array (
'firstTpl'		=> '<a href="[[~[[*id]]? &offset=`0`]]">&laquo; First</a> &nbsp;',
'lastTpl' 		=> '&nbsp;<a href="[[~[[*id]]? &offset=`[[+offset]]`]]">Last &raquo;</a>',
'prevTpl' 		=> '<a href="[[~[[*id]]? &offset=`[[+offset]]`]]">&lsaquo; Prev.</a>&nbsp;',
'nextTpl' 		=> '&nbsp;<a href="[[~[[*id]]? &offset=`[[+offset]]`]]">Next &rsaquo;</a>',
'currentPageTpl'=> '&nbsp;<span>[[+page_number]]</span>&nbsp;',
'pageTpl' 		=> '&nbsp;<a href="[[~[[*id]]? &offset=`[[+offset]]`]]">[[+page_number]]</a>&nbsp;',
'outerTpl' 		=> '<div id="pagination">[[+content]]<br/>
	Page [[+current_page]] of [[+page_count]]<br/>
	Displaying records [[+first_record]] thru [[+last_record]] of [[+record_count]]
</div>',
);
/*EOF*/