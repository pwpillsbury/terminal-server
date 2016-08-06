<?php
include("varstermrouter.inc");
$cxn = mysql_connect($host, $user, $password);

$username = $_REQUEST[user];
$serverid = $_REQUEST[sid];
$password = $_REQUEST[pswd];
$deleteafter = $_REQUEST[delafter];

// Connect database.
$tablename = "proj_data"; 
mysql_select_db($database);



// Functions for export to excel.
function xlsBOF()
    {
        echo pack("ssssss", 0x809, 0x8, 0x0, 0x10, 0x0, 0x0);
        return;
    }

function xlsEOF()
    {
        echo pack("ss", 0x0A, 0x00);
        return;
    }

function xlsWriteNumber($Row, $Col, $Value)
    {
        echo pack("sssss", 0x203, 14, $Row, $Col, 0x0);
        echo pack("d", $Value);
        return;
    }

function xlsWriteLabel($Row, $Col, $Value )
    {
        $L = strlen($Value);
        echo pack("ssssss", 0x204, 8 + $L, $Row, $Col, 0x0, $L);
        echo $Value;
        return;
    }

// file name for download
//
//$filename = "website_data_" . date('Ymdhns') . ".xls";
$filename = "website_data_" . date('Ymd') . ".xls";

// Tell browser that output from this page is a file for downloading
//
header("Pragma: public");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Content-Type: application/force-download");
header("Content-Type: application/octet-stream");
header("Content-Type: application/download");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Content-Transfer-Encoding: binary ");

// Output file header
//
xlsBOF();

// Make a top line on your excel sheet at line 1 (starting at 0).
// The first number is the row number and the second number is the column, both start at '0'
// That is, row=0 in function call here means "row 1" on your worksheet
//
xlsWriteLabel(0,0,"Terminal Data");

/**/
// Make static column labels. (at line 3)
// 
xlsWriteLabel(2,1,"Terminal ID");
xlsWriteLabel(2,2,"Program Index");
xlsWriteLabel(2,3,"Data");
/**/

/* 
// ...or
// Get table column names to use as column labels in the worksheet.
// 
mysql_select_db("information_schema");
$query = "SELECT * FROM columns WHERE table_name = '" . $tablename . "';";
$result=mysql_query($query) or die ("<DIV>ERROR<DIV>Could not execute schema search query");


// ...and Write out column names if collected from schema
//
$xlsCol = 1;
while($row=mysql_fetch_array($result))
    {
        xlsWriteLabel(2,$xlsCol,$row['COLUMN_NAME']);
        //xlsWriteNumber($xlsRow,0,$row['id']);
        //xlsWriteLabel($xlsRow,1,$row['name']);
        $xlsCol++;
    }
*/


// Get data records from terminal data table.
//
mysql_select_db($database);
//      eg.: $result=mysql_query("select * from " . $tablename . " order by datestamp asc");
$query = "SELECT termid,progpointer,data from " . $tablename . " WHERE "
."user=\"" .$username."\" AND password=\"".$password."\" AND serverid=\"".$serverid."\" "
// this ensures data for each transaction are grouped together. That is, the correct "quantity" associated
// with each "item" always immediately follows the the "item" in your worksheet data
."ORDER BY termid, autoinc";  

$result=mysql_query($query);
$row="";
$xlsRow = 3;

// Get data records from mysql 
//
while($row=mysql_fetch_array($result, MYSQL_NUM))
    {
        $xlsCol = 1;
        foreach ($row as $value)
            {
                xlsWriteLabel($xlsRow,$xlsCol,$value);
                //if always numeric, can also use: xlsWriteNumber($xlsRow,0,$value);
                //can also access row array like: xlsWriteLabel($xlsRow,1,$row['data']);
                $xlsCol++;
            }
        $xlsRow++;
    }

xlsEOF();

// Check to see if should delete records after export
//
if (($deleteafter == "yes") or ($deleteafter == "YES") ) {
    $query = "DELETE FROM " . $tablename . " WHERE "
    ."user=\"" .$username."\" AND password=\"".$password."\" AND serverid=\"".$serverid."\";";

    $result=mysql_query($query);
}

exit();

?>