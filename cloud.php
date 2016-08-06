<?php
/*
 * Revision 2
 * Includes PID with serial and program statement num tracking, &PID=
 * Includes checksum &CKS= support
 * Still works with rev 1 devices (no PID, CKS)
 * 
 */

function clean_data($cxn, $string)
{
    //$string = strip_tags($string);
    return mysqli_real_escape_string($cxn, $string);
}

function do_status($dodie,$errmsg)
{
    global $inUser;
    global $inPassword;
    global $inTermID;
    global $inServID;
    global $cxn;
/*
 *implement this with your own error handling...
 */
    $query = "INSERT INTO messages (user, password, termid, serverid, data) VALUES "
    ."(\"" . $inUser . "\",\"" . $inPassword . "\",\"" . $inTermID . "\",\"" . $inServID . "\",\"" . $errmsg . "\");";
    $result = mysqli_query($cxn,$query) or die("wd*error:Could not load messages table");
    if ($dodie==1){
        die();
    }
    return;
}

function isLastPrompt($PID)
{
    // This routine check the program table for the highest numbered prompt statement
    // A complex program may have to determine the end-of-transaction using different means, which you will have to determine
    global $cxn;
    $sql = "SELECT progpointer FROM proj_program ORDER BY progpointer DESC;";
    $result = mysqli_query($cxn,$sql) or do_status(0,"wd*error:Couldn't read from proj_program table");
    if ($result > 0) {
      $row = mysqli_fetch_assoc($result);
      $progptr = $row['progpointer'];
    } else {  // broken program table
      $progptr = 0;
    }
    return ($progptr <= $PID);
}


function isEndOfTransaction($PID,$TID)
        // Use this for custom end-of-transx, as in multi-mode menu driven terminal applications
{
    return(false);
}


function SaveTerminalData($inUser,$inPassword,$inServID,$inTermID,$progpointer,$inData)
        // called by CommitTrnsx
        // While transaction is in process, data is stored in trnsx table
        // When transaction completes, data is written to data table here
{
    global $cxn;
    $query = "INSERT INTO proj_data (user,password,serverid,termid, progpointer, data) VALUES "
    ."(\"" . $inUser . "\",\"" . $inPassword . "\",\"" . $inServID . "\",\"" . $inTermID . "\",\"" . $progpointer . "\",\"" . $inData . "\");";
    $result = mysqli_query($cxn,$query) or do_status(0,"wd*error:Couldn't add data to proj_data table");
}


/* write terminal data to transx table
 * 
 */
function updateTrnsx($inUser, $inPassword, $inServID, $PID, $TID, $Tdata)
{
    global $cxn;
    global $promptcounter;
    global $trnsxcounter;
    // First check if record exists and update if so
    $sql = "SELECT * FROM proj_trnsx  WHERE termid = \"" . $TID . "\" AND progpointer = \"" . $PID . "\";";
    $trnsxcounter = getTrnsxCounter($TID, false); // get value here. No increment on trsx update
    $result = mysqli_query($cxn,$sql) or do_status(0,"wd*error:Couldn't read from proj_program table");
    if (mysqli_fetch_row($result)) {
        do_status(0,"wd*status:Updating transaction #$trnsxcounter");
        $query = "UPDATE proj_trnsx SET data = \"" . $Tdata . "\" WHERE termid = \"" . $TID . "\" AND progpointer = \"" . $PID . "\";"; 
        $promptcounter = getPromptCounter($TID, false); // returns incrementing value to embed in prompt
    } else {
        do_status(0,"wd*status:Writing transaction #$trnsxcounter");
        $query = "INSERT INTO proj_trnsx (user,password,serverid,termid, progpointer, data) VALUES "
        ."(\"" . $inUser . "\",\"" . $inPassword . "\",\"" . $inServID . "\",\"" . $TID . "\",\"" . $PID . "\",\"" . $Tdata . "\");";
        $promptcounter = getPromptCounter($TID, true); // returns incrementing value to embed in prompt
    }
    $result = mysqli_query($cxn,$query) or do_status(0,"wd*error:Couldn't add or update data to proj_trnsx table");
}


/*
 * writes out data from trnsx table to working data
 * clears trnsx table for next cycle
 */
function commitTrnsx($PID,$TID)
{
    global $cxn;
    global $trnsxcounter;
    // First copy data to main data table
    $sql = "SELECT * FROM proj_trnsx  WHERE termID = \"" . $TID . "\" ORDER BY progpointer;";
    $result = mysqli_query($cxn,$sql) or do_status(0,"wd*error:Couldn't read from proj_trnsx table");
    if (mysqli_num_rows($result)>0) {
        do_status(0,"wd*status:Commiting transaction #$trnsxcounter");
        while ($row = mysqli_fetch_assoc($result)) {
            SaveTerminalData($row['user'],$row['password'],$row['serverid'],$row['termid'],$row['progpointer'],$row['data']);
        }
    } else {  
        do_status(0,"wd*error:Couldn't commit trnsx");
    }
    
    // Then delete transx record
    $sql = "DELETE FROM proj_trnsx  WHERE termID = \"" . $TID . "\";";
    $result = mysqli_query($cxn,$sql) or do_status(0,"wd*error:Couldn't delete from proj_trnsx table");
   
    // Then create $wantFirst flag
    // currently unused flag construct, but can be used to track/control transaction start process
    $sql = "INSERT INTO proj_trnsx (termid, progpointer) VALUES "
    ."(\"" . $TID . "\",\" 0 \");";
    $result = mysqli_query($cxn,$sql) or do_status(0,"wd*error:Couldn't add wantFirst flag to proj_trnsx table");

    $trnsxcounter = getTrnsxCounter($TID, true); // returns incrementing value to embed in prompt
}


/*
function clearWantFirst($TID)
{
    global $cxn;
    $sql = "DELETE FROM proj_trnsx  WHERE termid = \"" . $TID . "\" AND progpointer = \"0\";";
    $result = mysqli_query($cxn,$sql) or do_status(0,"wd*error:Couldn't delete wantFirst flag");
    return ($result > 0);
}


function getWantFirst($TID)
{
    global $cxn;
    $sql = "SELECT * FROM proj_trnsx  WHERE termid = \"" . $TID . "\" AND progpointer = \"0\";";
    $result = mysqli_query($cxn,$sql) or do_status(0,"wd*error:Couldn't read from proj_program table");
    return (mysqli_num_rows($result) > 0); 
}
*/


// Serial value is 0..9 and is kept separately for each connected Terminal
function GetNextSer($inTermID)
{
    global $cxn;
// see if there is already a record for this terminal
    $num = 0;
    $sql = "SELECT * FROM proj_terminals  WHERE termID = \"" . $inTermID . "\";";
    $result = mysqli_query($cxn,$sql) or do_status(0,"wd*error:Couldn't read from proj_terminals table");
    $num = mysqli_num_rows($result);
    if ($num > 0) {
        $row = mysqli_fetch_assoc($result);
        $ser = $row['serial'];
        if ($ser < 9) {
            $ser = $ser + 1;
        } else {
            $ser = 0;
        }
    } else {  // if termid not found. This is an error handled elsewhere
        return 0;
    }
    
    if ($num > 0) // record found, update it
    {
        $row = mysqli_fetch_assoc($result);
        $sql = "UPDATE proj_terminals SET serial = \"" . $ser . "\" WHERE termID = \"" . $inTermID . "\";";
        $result = mysqli_query($cxn,$sql) or do_status(0,"wd*error:Couldn't update terminals table serial");
    }
    return $ser;
}

function GetCurSer($inTermID)
{
    global $cxn;
// see if there is already a record for this terminal
    $num = 0;
    $sql = "SELECT * FROM proj_terminals  WHERE termID = \"" . $inTermID . "\";";
    $result = mysqli_query($cxn,$sql) or do_status(0,"wd*error:Couldn't read from proj_terminals table");
    $num = mysqli_num_rows($result);
    if ($num > 0) {
        $row = mysqli_fetch_assoc($result);
        $ser = $row['serial'];
        return $ser;
    } else {  // if termid not found. This is an error handled elsewhere
        return 0;
    }
}


//function GetProgPointer(&$progptr,$inTermID)
function GetProgPointer($inTermID)
{
    global $cxn;
	// Check for termid in proj_terminals, get progpointer
    $sql = "SELECT * FROM proj_terminals  WHERE termID = \"" . $inTermID . "\";";
    $result = mysqli_query($cxn,$sql) or do_status(0,"wd*error:Couldn't read from proj_terminals table");
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $progptr = $row['progpointer'];
    } else {  // if termid not found, error: reset, treat as sign-in, or?
    	//same as sign-in
        $progptr = 0;
    }
    return $progptr;
}


function GetNextProgPointer($progptr)
{
    global $cxn;
    $sql = "SELECT * FROM proj_program  WHERE progpointer = \"" . $progptr . "\";";
    $result = mysqli_query($cxn,$sql) or do_status(0,"wd*error:Couldn't read from proj_program table");
    if (mysqli_num_rows($result) > 0) {
      $row = mysqli_fetch_assoc($result);
      $progptr = $row['on_yes'];
    } else {  // broken program table
      //$progpointer = 0;
    }
    return $progptr;
}


function getPromptCounter($inTermID,$increment)
{
    global $cxn;
    $sql = "SELECT * FROM proj_terminals  WHERE termID = \"" . $inTermID . "\";";
    $result = mysqli_query($cxn,$sql) or do_status(0,"wd*error:Couldn't read from proj_terminals table");
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $promptctr = $row['counter'];

    } else {  // if termid not found, error: reset, treat as sign-in, or?
    // terminals table no longer used with PID version. Only used here for prompt counter display    
        $promptctr = 0;
        $sql = "INSERT INTO proj_terminals (termid, counter) VALUES (\"" . $inTermID . "\",\"" . $promptctr . "\");";
        $result = mysqli_query($cxn,$sql) or do_status(0,"wd*error:Couldn't update terminals table");
    }
    if ($increment) {
        $promptctr++;
        $sql = "UPDATE proj_terminals SET counter = \"" . $promptctr . "\" WHERE termID = \"" . $inTermID . "\";";
        $result = mysqli_query($cxn,$sql) or do_status(0,"wd*error:Couldn't update terminals table promptcounter");
    }
    return $promptctr;
}


function getTrnsxCounter($inTermID,$increment)
{
    global $cxn;
    $sql = "SELECT * FROM proj_terminals  WHERE termID = \"" . $inTermID . "\";";
    $result = mysqli_query($cxn,$sql) or do_status(0,"wd*error:Couldn't read from proj_terminals table");
    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        $trnsxctr = $row['trnsx_counter'];

    } else {  // if termid not found, error: reset, treat as sign-in, or?
    // terminals table no longer used with PID version. Only used here for prompt counter display    
        $trnsxctr = 0;
        $sql = "INSERT INTO proj_terminals (termid, trnsx_counter) VALUES (\"" . $inTermID . "\",\"" . $trnsxctr . "\");";
        $result = mysqli_query($cxn,$sql) or do_status(0,"wd*error:Couldn't update terminals table");
    }
    if ($increment) {
        $trnsxctr++;
        $sql = "UPDATE proj_terminals SET trnsx_counter = \"" . $trnsxctr . "\" WHERE termID = \"" . $inTermID . "\";";
        $result = mysqli_query($cxn,$sql) or do_status(0,"wd*error:Couldn't update terminals table promptcounter");
    }
    return $trnsxctr;
}



// Not used for firmware rev > 332F
function UpdateTerminalRecord($inTermID,$progpointer) 
{
    global $cxn;
// see if there is already a record for this terminal
    $num = 0;
    $sql = "SELECT * FROM proj_terminals  WHERE termID = \"" . $inTermID . "\";";
    $result = mysqli_query($cxn,$sql) or do_status(0,"wd*error:Couldn't read from proj_terminals table");
    $num = mysqli_num_rows($result);
    if ($num > 0) {
        $row = mysqli_fetch_assoc($result);
    } else {  // if termid not found, then assume Sign-In and initialize
        $progpointer = 1;
    }
    if ($num > 0) // record found, update it
    {
        // Return server's reply to Terminal and delete from transfer table
        $row = mysqli_fetch_assoc($result);
        $sql = "UPDATE proj_terminals SET progpointer = \"" . $progpointer . "\" WHERE termID = \"" . $inTermID . "\";";
        $result = mysqli_query($cxn,$sql) or do_status(0,"wd*error:Couldn't update terminals table progpointer");
    }
    elseif ($num == 0) // no record found, so add one
    {
        $sql = "INSERT INTO proj_terminals (termid, progpointer) VALUES (\"" . $inTermID . "\",\"" . $progpointer . "\");";
        $result = mysqli_query($cxn,$sql) or do_status(0,"wd*error:Couldn't update terminals table");
    }
//    do_status(0,"updated termrec: $inTermID , $progpointer");
}



/*
 * Compare incoming PID (Packet Identifier) with current terminal parameters
 */
function ValidateIncoming($pktID, $pktSer, $inTermID, $chkStr, $inData)  
{
    $aSer = GetCurSer($inTermID);
    $progptr = GetProgPointer($inTermID);
    do_status(0,"validating: pktid:$pktID , progptr:$progptr, pktser:$pktSer, aser:$aSer");
    $aResult1 = ( ($pktID == $progptr) and ($pktSer == $aSer) );
    
    $aResult2 = true;
    if ($chkStr != "") {  // checksum is set
    // Strip any leading zeros
        $chkStr = ltrim($chkStr,"0");
        $chrs = str_split($inData);
        $aSum = 0;
        foreach ($chrs as $achr) {
            $aSum = $aSum ^ ord($achr); // xor
//            do_status(0,"wd*status:aSum = $aSum");            
        }
        $aResult2 = ($aSum == $chkStr);
        if ($aResult2 != true) {
            do_status(0,"wd*status:Bad Checksum (recd: $chkStr, calcd: $aSum), data ignored: $inData");            
        } else {
//            do_status(0,"wd*status:Good Checksum (recd: $chkStr, calcd: $aSum)");            
        }
    }
    if ($aResult1 != true) do_status(0,"wd*status:Bad Serial/PID Match, data ignored: $inData");            
    return ( $aResult1 and $aResult2 ); 
}        


// if no PID then use this proc
function SendPrompt($progpointer, $promptcounter)  
{
    global $cxn;
    $sql = "SELECT * FROM proj_program  WHERE progpointer = \"" . $progpointer . "\";";
    $result = mysqli_query($cxn,$sql) or do_status(0,"wd*error:Couldn't read from proj_program table");
    if (mysqli_num_rows($result) > 0) {
        // Return command reply from program table to Terminal
        $row = mysqli_fetch_assoc($result);
        echo "wd*data:" . $row['cmd'] ." (prompt #:" . $promptcounter ."): " . chr(4);
    } else {  // broken program table
        do_status(0,"wd*error:Broken Program Table");
    }
}

/*
function SendPromptNew($progpointer, $pcounter, $tcounter)
{
    global $cxn;
    $sql = "SELECT * FROM proj_program  WHERE progpointer = \"" . $progpointer . "\";";
    $result = mysqli_query($cxn,$sql) or do_status(0,"wd*error:Couldn't read from proj_program table");
    if (mysqli_num_rows($result) > 0) {
        // Return command reply from program table to Terminal
        $row = mysqli_fetch_assoc($result);
        $tagstr = str_pad($progpointer, 3, "0", STR_PAD_LEFT);
        do_status(0,"wd*status:Sending Prompt: ". $tagstr);
        echo "wd*d" . $tagstr . ":" . $row['cmd'] ." (prmpt#:" . $pcounter .", trx#:" . $tcounter . "): " . chr(4);
    } else {  // broken program table
        do_status(0,"wd*error:Broken Program Table");
    }
}
*/


// Use for PID enabled terminal, firmware rev >332f
function SendPromptNewSerial($inTermID, $progpointer, $pcounter, $tcounter, $isError)
{
    global $cxn;

    do_status(0,"wd*status:sending prompt: $progpointer");
    
    $sql = "SELECT * FROM proj_program  WHERE progpointer = \"" . $progpointer . "\";";
    $result = mysqli_query($cxn,$sql) or do_status(0,"wd*error:Couldn't read from proj_program table");
    if (mysqli_num_rows($result) > 0) {
    // Return command reply from program table to Terminal
        $row = mysqli_fetch_assoc($result);
    // Build PID string to send to Terminal for return data validation
        $tagstr = str_pad($progpointer, 2, "0", STR_PAD_LEFT);  // give 2-char string, allows programs up to 99 statements
        $serstr = GetNextSer($inTermID);
    // tagstr (PID) is 3 characters: 
    //   1st char is a "serial num" that repeatedly increments 0..9 with every prompt    
    //   2nd and 3rd are the program statement number and allows programs up to 99 statements
        $tagstr = $serstr . $tagstr;
        
    // Build your prompt string
    // Of course, you will want to customize this and remove the demo status indicators for prompt and trnsx counting...   
        $cmd_prefix = "wd*d" . $tagstr . ":";   // required
        if ($isError) {
            $cmd_statement = "@B3" . $row['cmd'];   // required, adds 3 beeps to prompt
        } else {
            $cmd_statement = $row['cmd'];           // required
        }
        $cmd_extra = " (prmpt#:" . $pcounter .", trx#:" . $tcounter . "): ";    // optional
        $cmd_terminator =  chr(4);              // required
        
//        $cmd = "wd*d" . $tagstr . ":" . $row['cmd'] ." (prmpt#:" . $pcounter .", trx#:" . $tcounter . "): " . chr(4);
        $cmd = $cmd_prefix . $cmd_statement . $cmd_extra . $cmd_terminator;
        
        do_status(0,"wd*status:Sending Prompt: ". $cmd);
        echo $cmd;
        //echo "wd*d" . $tagstr . ":" . $row['cmd'] ." (prmpt#:" . $pcounter .", trx#:" . $tcounter . "): " . chr(4);
        
        UpdateTerminalRecord($inTermID,$progpointer); // so GetProgPointer returns current prompt for this terminal when we want to validate returned data
        
    } else {  // broken program table
        do_status(0,"wd*error:Broken Program Table");
    }
}



/************************************************************************
Begin Main Body
************************************************************************/

// Load vars and connect to DB
include("../../protected/varstermrouter.inc");
$cxn = mysqli_connect($host, $user, $password,$database );
mysqli_select_db ($cxn, $database);

/****
1. Clean incoming data in case this is a hacking attempt
****/

$inUser = clean_data($cxn, $_REQUEST['user']);
$inPassword = clean_data($cxn, $_REQUEST['pswd']);
$inTermID = clean_data($cxn, $_REQUEST['termID']);
$inServID = clean_data($cxn, $_REQUEST['serverID']);

if (isset($_REQUEST['PID'])) {
	$pktStr = clean_data($cxn, $_REQUEST['PID']); // always 4 digits string echos progID sent with prompt data
} else {
	$pktStr = "";
}	

if (isset($_REQUEST['CKS'])) {
	$chkStr = clean_data($cxn, $_REQUEST['CKS']); // always 4 digits string echos progID sent with prompt data
} else {
	$chkStr = "";
}	

$inData = clean_data($cxn, $_REQUEST['termdata']);

/*
// PID support is 332F and later
*/

if ($pktStr != "") {  // PID is set
   
// First break into ser and progpointer components
    $pktSer = substr($pktStr,0,1);  // take the 1st digit (0..9)
    $pktID = substr($pktStr,1,2);  // take the 2nd two digits
    
// Strip any leading zeros
    $pktID = ltrim($pktID,"0");
// pktID is program pointer
// pktSer is serial code for this data payload
// Both must match record for indicated Terminal before recording data, advancing program, and sending next next prompt    
    
    
/****
 If SignIn, 
 * 
  -- delete all references to this terminal in the terminal tracking table and trnsx table so it starts new transaction, 
****/
    if (strpos ($inData, 15) != 0) {
    //if (strpos ($inData, '\x0F') != 0) {
        do_status(0,"wd*status:Terminal SignIn Detected: $inData");

// First clear out any old trnsx record...

        $query = "DELETE FROM proj_trnsx  WHERE  termid=\"" . $inTermID . "\"";
        $result = mysqli_query($cxn,$query) or do_status(0,"wd*error:Couldn't execute query delete proj_trnsx on signin");// die("wd*error:Couldn't execute query 1b delete fromterminal");
        if ($result){
            do_status(0,"wd*warning:deleted proj_trnsx records on SignIn");
        }

// Then clear out the old terminal record for SignIn...

        $query = "DELETE FROM proj_terminals  WHERE  termid=\"" . $inTermID . "\"";
        $result = mysqli_query($cxn,$query) or do_status(0,"wd*error:Couldn't execute query 1b delete proj_terminals");// die("wd*error:Couldn't execute query 1b delete fromterminal");
        if ($result){
            do_status(0,"wd*warning:deleted proj_terminal records on SignIn");
        }
	
// Initialize various counters...

        $progpointer = 1;   // 1 or wherever your program entry point is. Necessary for all server implementation
        $promptcounter = 1; // specific to this demo
        $trnsxcounter = 0;  // specific to this demo
        
    
    } 
/****
 Else, special handling data
 
 ****/
    elseif ($inData == chr(28)) {           // These characters from terminal are not handled in this demo
    	;//UpArrow
    }
    elseif ($inData == chr(29)) {
    	;//DownArrow;
    }
    elseif ($inData == chr(30)) {
	;//LeftArrow;
    }
    elseif ($inData == chr(31)) {
	;//RightArrow;
    }
    elseif ($inData == chr(23)) {
	;//BeginKey;
    }
    elseif ($inData == chr(24)) {
	;//EndKey;
    }
    elseif ($inData == chr(11)) {
	;//SearchKey;
    }
    elseif ($inData == chr(14)) {
	//signout
        $progpointer = -1;
    }
    
    elseif ( ValidateIncoming($pktID, $pktSer, $inTermID, $chkStr, $inData) ) { // Regular Data...
    /****
     If LastPrompt in your program sequence (end-of-transaction)
     - Set program to start  
     ****/
        if (isLastPrompt($pktID) or isEndOfTransaction($pktID,$inTermID)) {
            do_status(0,"wd*status:Terminal Data Detected (last): $inData");
            updateTrnsx($inUser,$inPassword,$inServID,$pktID,$inTermID,$inData);  // calls GetPromptCounter
            commitTrnsx($pktID,$inTermID);  // this sets $wantFirst record/flag
            $progpointer = 1; 
        }
        else {
        /****
         Else, regular data
         ****/
            do_status(0,"wd*status:Terminal Data Detected: $inData");
            updateTrnsx($inUser,$inPassword,$inServID,$pktID,$inTermID,$inData);  // calls GetPromptCounter
            $progpointer = GetNextProgPointer($pktID);  // this is $pktID
        }
    } else { 
    // invalid serial/pktID
        $trnsxcounter = getTrnsxCounter($inTermID, false); // get value here. No increment
        $progpointer = GetProgPointer($inTermID);  // this is $pktID
        if ($progpointer == 0) $progpointer = 1;
        SendPromptNewSerial($inTermID, $progpointer, $progpointer, $trnsxcounter, true); // repeat send previous prompt with added beep
        exit; 
        
    }

    // This may fail/timeout if terminal has disconnected, so clear wantFirst flag AFTER
    SendPromptNewSerial($inTermID, $progpointer, $progpointer, $trnsxcounter, false);
    
// Clear $wantFirst flag for this terminal
    //if ($pktID == 1) clearWantFirst($inTermID);
//    clearInProcess($inTermID);  // update inProcess table to allow data receive
    
    $query = "INSERT INTO terminallog (user, password, termid, serverid, data) VALUES (\"" . $inUser . "\",\"" . $inPassword . "\",\"" . $inTermID . "\",\"" . $inServID . "\",\"" . $inData . " :disconnect" . "\");";
    $result = mysqli_query($cxn,$query) or do_status(0,"wd*error:Couldn't add Terminal Log Entry");// die("wd*error:Couldn't execute terminal log entry");
    
    
    
    
} else { //No PID. Chcksum only supported with PID

    
    
    
/*
// Only runs for firmware revs before 332F
*/
    
    
/*    
 If SignIn, delete all references to this terminal in the terminal tracking table so it starts new transaction
 * 
 */
    
    if ($inData == chr(15)) {
        $query = "DELETE FROM proj_terminals  WHERE  termid=\"" . $inTermID . "\"";
        $result = mysqli_query($cxn,$query) or do_status(0,"wd*error:Couldn't execute query 1b delete proj_terminals");// die("wd*error:Couldn't execute query 1b delete fromterminal");
        if ($result){
            do_status(0,"wd*warning:deleted proj_terminal records on SignIn");
        }
        $progpointer = 0; // use 0 to indicate a "sign-in" where no data is associated with this communication from the Terminal
    } 
    elseif ($inData == chr(28)) {           // These characters from terminal are not handled in this demo
	;//UpArrow
    }	                                          
    elseif ($inData == chr(29)) {	
	;//DownArrow;
    }	
    elseif ($inData == chr(30)) { 
	;//LeftArrow;
    }	
    elseif ($inData == chr(31)) { 
	;//RightArrow;
    } 	
    elseif ($inData == chr(23)) { 
	;//BeginKey;
    }
    elseif ($inData == chr(24)) { 
	;//EndKey;
    }
    elseif ($inData == chr(11)) { 
	;//SearchKey;
    }
    elseif ($inData == chr(14)) { 
	//signout
        $progpointer = -1;
	
    }
    else {
	// regular data, so...
	// Check for termid in proj_terminals, get progpointer
        $sql = "SELECT * FROM proj_terminals  WHERE termID = \"" . $inTermID . "\";";
        $result = mysqli_query($cxn,$sql) or do_status(0,"wd*error:Couldn't read from proj_terminals table");
        if ($result > 0)
        {
            $row = mysqli_fetch_assoc($result);
            $progpointer = $row['progpointer'];
        } else {  // if termid not found, error: reset, treat as sign-in, or?
        	//same as sign-in
            $progpointer = 0;
        }
    }	


/*
 Load the terminal data to the appropriate table(s)
 * 
 */
    if ($progpointer > 0) { //not a sign-in/sign-out
        $query = "INSERT INTO proj_data (user,password,serverid,termid, progpointer, data) VALUES "
        ."(\"" . $inUser . "\",\"" . $inPassword . "\",\"" . $inServID . "\",\"" . $inTermID . "\",\"" . $progpointer . "\",\"" . $inData . "\");";
        $result = mysqli_query($cxn,$query) or do_status(0,"wd*error:Couldn't add data to proj_data table");
    }

//echo "<br />progptr: $progpointer<br />";

/*
  Get the next prompt command for this terminal
 * 
 */
    $sql = "SELECT * FROM proj_program  WHERE progpointer = \"" . $progpointer . "\";";
    $result = mysqli_query($cxn,$sql) or do_status(0,"wd*error:Couldn't read from proj_program table");
    if ($result > 0) {
        $row = mysqli_fetch_assoc($result);
        $progpointer = $row['on_yes'];
    } else {  // broken program table
        //$progpointer = 0;
    }

    $sql = "SELECT * FROM proj_program  WHERE progpointer = \"" . $progpointer . "\";";
    $result = mysqli_query($cxn,$sql) or do_status(0,"wd*error:Couldn't read from proj_program table");
    if ($result > 0) {
        // Return command reply from program table to Terminal
        $row = mysqli_fetch_assoc($result);
        echo "wd*data:" . $row['cmd'] . chr(4);
    } else {  // broken program table
        //$progpointer = 0;
    }


/*
 Update terminal record in proj_terminal table
 see if there is already a record for this terminal
 * 
 */
    
    UpdateTerminalRecord($inTermID,$progpointer);
    
            
/*
  Load log information (optional)
 * 
 */
    $query = "INSERT INTO terminallog (user, password, termid, serverid, data) VALUES (\"" . $inUser . "\",\"" . $inPassword . "\",\"" . $inTermID . "\",\"" . $inServID . "\",\"" . $inData . "\");";
    $result = mysqli_query($cxn,$query) or do_status(0,"wd*error:Couldn't add Terminal Log Entry");// die("wd*error:Couldn't execute terminal log entry");


} // no PID



?>

