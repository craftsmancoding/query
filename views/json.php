<?php
/*------------------------------------------------------------------------------
This view outputs the record data as JSON. This is useful for creating AJAX
stores.
------------------------------------------------------------------------------*/
$data = array(
    'results' => $data,
    'total' => $page_count,
);
print json_encode($data);
/*EOF*/