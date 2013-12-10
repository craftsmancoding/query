<?php
/*------------------------------------------------------------------------------
This view outputs the record data as JSON. This is useful for creating AJAX
stores.
------------------------------------------------------------------------------*/
$data = array(
    'results' => $data,
    'total' => $total_pages,
);
print json_encode($data);
/*EOF*/