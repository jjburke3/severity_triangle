<?php
$state = $_GET['state'];
// credentials for both florida and texas; the ones used depends on $state variable
$servername = "";
$username = "";
$password = "";
$dbname = "";
if($state == "FL"){
	// Create connection
	$conn = mysqli_connect($servername, $username, $password, $dbname);

	// Check connection
	if (!$conn) {
		die("Connection failed: " . mysqli_connect_error());
	}
} else {
	//create an instance of the  ADO connection object
	$conn = new COM ("ADODB.Connection")
	  or die("Cannot start ADO");

	//define connection string, specify database driver
	$connStr = "PROVIDER=SQLOLEDB;SERVER=".$servername.";UID=".$username.";PWD=".$password.";DATABASE=".$dbname; 
	  $conn->open($connStr); //Open the connection to the database
}

// default all ec variables to blank
$sql_ec = "";
$sql_ec_report = "";
$sql_expense = "";

if($_GET['loss'] == "PAID" or $_GET['loss'] == "PAIDTIME") // use only paid amounts
{
	if($_GET['ec'] == "RECOVONLY") // limit to recovery amounts
	{
		$sql_loss = "RECOV_PAID";
	} elseif($_GET['ec'] == "NORECOV") // exclude recovery amounts
	{
		$sql_loss = "CLAIM_PAID";
	} else { // include recovery amounts
		$sql_loss = "CLAIM_PAID+RECOV_PAID";
	}
}
elseif($_GET['loss'] == "INCUR" or $_GET['loss'] == "INCURTIME") // use paid and reserve amount
{
	if($_GET['ec'] == "RECOVONLY") // limit to recovery amounts
	{
		$sql_loss = "RECOV_PAID+RECOV_RES";
	} elseif($_GET['ec'] == "NORECOV") // exclude recovery amounts
	{
		$sql_loss = "CLAIM_PAID+CLAIM_RES";
	} else { // include recovery amounts
		$sql_loss = "CLAIM_PAID+RECOV_PAID+CLAIM_RES+RECOV_RES";
	}
}
else
{
	$sql_loss = "CLAIM_PAID+RECOV_PAID";
}
if($_GET['ec'] == "LITONLY")
{
	$sql_loss = "LIT_".str_replace("+","+LIT_",$sql_loss); // limit to only lit amounts
}

if($_GET['county'] == "ALL") // use default tables when no county is specified
{
	$sql_county_table = "";
	$sql_county_where = "";
}
elseif($_GET['county'] == "OTHER") // use county version of tables and exclude 6 main counties
{
	$sql_county_table = "_county";
	$sql_county_where = " and COUNTY not in ('MIAMI-DADE','BROWARD','PINELLAS','HILLSBOROUGH','PALM BEACH','DUVAL')";
}
else // use county version of tables and limit to specified county
{
	$sql_county_table = "_county";
	$sql_county_where = " and COUNTY = '".$_GET['county']."'";
}
// this where statement variable will go into case statements in query
$where_statement = " PROGRAM like '".$_GET['prog']."' AND COVERAGE like '".$_GET['cov']."'".$sql_county_where." ";

if($_GET['time'] == "WEEK")
{	// set columns called, and time shift for lag periods; function is different in mysql and sql server
	$sql_month = "REPORT_WEEK AS REPORT_MONTH, LAG_WEEK AS LAG_MONTH,
	CASE WHEN 
	".($state=="FL"?"DATE_ADD(RIGHT(REPORT_WEEK,10), INTERVAL LAG_WEEK WEEK)":
	"dateadd(week,LAG_WEEK,right(REPORT_WEEK,10))")."
	BETWEEN '2005-01-01' AND 
	".($state=="FL"?"CURDATE()":"convert(date,getdate())")."
	THEN CONCAT('Week of ',
	".($state=="FL"?"DATE_ADD(RIGHT(REPORT_WEEK,10), INTERVAL LAG_WEEK WEEK))":
	"dateadd(week,LAG_WEEK,right(REPORT_WEEK,10)))")."
	END AS CALENDAR_MONTH";
	$sql_group = "REPORT_WEEK, LAG_WEEK,
	CASE WHEN 
	".($state=="FL"?"DATE_ADD(RIGHT(REPORT_WEEK,10), INTERVAL LAG_WEEK WEEK)":
	"dateadd(week,LAG_WEEK,right(REPORT_WEEK,10))")."
	BETWEEN '2005-01-01' AND 
	".($state=="FL"?"CURDATE()":"convert(date,getdate())")."
	THEN CONCAT('Week of ',
	".($state=="FL"?"DATE_ADD(RIGHT(REPORT_WEEK,10), INTERVAL LAG_WEEK WEEK))":
	"dateadd(week,LAG_WEEK,right(REPORT_WEEK,10)))")."
	END ";
	$sql_lag = "0 = 0";
	$sql_table = "_week";
	$report_where = "where REPORT_WEEK <> concat('Week of ',".($state=="FL"?"curdate()":"convert(date,getdate())").") 
		and REPORT_WEEK not like 'Week of 2006%'";
	if($_GET['type'] == "CALENDAR")
	{
		$report_where = $report_where.($state=="FL"?" 
			and DATE_ADD(RIGHT(REPORT_WEEK,10), INTERVAL LAG_WEEK WEEK) > '2007-01-01'
			and DATE_ADD(RIGHT(REPORT_WEEK,10), INTERVAL LAG_WEEK WEEK) < curdate() ":
			" and dateadd(week, LAG_WEEK,right(REPORT_WEEK,10) > '2015-01-01'
			and dateadd(week, LAG_WEEK,right(REPORT_WEEK,10) < convert(date,getdate()) ");
	}
}
else
{ // month version of column calls and lag calculation
	$sql_month = "REPORT_MONTH AS REPORT_MONTH, LAG_MONTH AS LAG_MONTH,
	".($state=="FL"?"
	case when PERIOD_ADD(REPLACE(REPORT_MONTH,'-',''),LAG_MONTH) > 0
	and date(concat(PERIOD_ADD(REPLACE(REPORT_MONTH,'-',''),LAG_MONTH),'01')) < curdate()
	then date_format(date(concat(PERIOD_ADD(REPLACE(REPORT_MONTH,'-',''),LAG_MONTH),'01')),'%Y-%m')
	end":
	"case when convert(varchar(6),dateadd(month,LAG_MONTH,concat(REPORT_MONTH,'-01')),112) > 0
		and dateadd(month,LAG_MONTH,concat(REPORT_MONTH,'-01')) < convert(date,getdate())
	then convert(varchar(7),dateadd(month,LAG_MONTH,concat(REPORT_MONTH,'-01')),121)
	end")." as CALENDAR_MONTH";
	$sql_group = "REPORT_MONTH, LAG_MONTH,
	".($state=="FL"?"
	case when PERIOD_ADD(REPLACE(REPORT_MONTH,'-',''),LAG_MONTH) > 0
	and date(concat(PERIOD_ADD(REPLACE(REPORT_MONTH,'-',''),LAG_MONTH),'01')) < curdate()
	then date_format(date(concat(PERIOD_ADD(REPLACE(REPORT_MONTH,'-',''),LAG_MONTH),'01')),'%Y-%m')
	end":
	"case when convert(varchar(6),dateadd(month,LAG_MONTH,concat(REPORT_MONTH,'-01')),112) > 0
		and dateadd(month,LAG_MONTH,concat(REPORT_MONTH,'-01')) < convert(date,getdate())
	then convert(varchar(7),dateadd(month,LAG_MONTH,concat(REPORT_MONTH,'-01')),121)
	end")." ";
	$sql_lag = "0 = 0";
	$sql_table = "_month";
	$report_where = "where REPORT_MONTH <> ".($state=="FL"?"date_format(curdate(),'%Y-%m')":"format(getdate(),'yyyy-MM')");
	if($_GET['type'] == "CALENDAR")
	{
		$report_where = $report_where.($state=="FL"?" 
			and date(concat(PERIOD_ADD(REPLACE(REPORT_MONTH,'-',''),LAG_MONTH),'01')) < date(date_format(curdate(),'%Y%m01'))
			and date(concat(PERIOD_ADD(REPLACE(REPORT_MONTH,'-',''),LAG_MONTH),'01')) >= '2007-01-01' ":"
			and format(dateadd(month,LAG_MONTH,concat(REPORT_MONTH,'-01')),'yyyyMM') < format(getdate(),'yyyyMM') 
			");
	}
}
// if ec excluded, than subtract amounts from existing calculations
if($_GET['ec'] == "YES")
{
	$sql_ec = "-sum(case when ".$where_statement." then EC_CLAIM_PAID+EC_RECOV_PAID end) ";
	$sql_ec_report = "-sum(case when ".$sql_lag." and ".$where_statement." then EC_REPORTED end)";
}
elseif($_GET['ec'] == "LIT") // subtract lit amounts from existing calculations
{
	if($_GET['loss'] == "PAID")
	{
		$sql_ec = "-sum(case when ".$where_statement." then LIT_CLAIM_PAID+LIT_RECOV_PAID end) ";
	}
	elseif($_GET['loss'] == "INCUR")
	{
		$sql_ec = "-sum(case when ".$where_statement." then LIT_CLAIM_RES+LIT_RECOV_RES+LIT_CLAIM_PAID+LIT_RECOV_PAID end) ";
	}
	else
	{
		$sql_ec = "-sum(case when ".$where_statement." then LIT_CLAIM_PAID+LIT_RECOV_PAID end) ";
	}
	$sql_ec_report = "-sum(case when ".$sql_lag." and ".$where_statement." then EC_REPORTED end)";
}
// bi is handled differently than other coverages, because limits can be specified for florida
if($_GET['cov'] == "BI%" && $state == "FL") {
	$limits = $_GET['limits'];
	$limits = "(COVERAGE = 'BI-".$limits."')";
	$limits = str_replace("_","' or COVERAGE = 'BI-",$limits);
	$limits = str_replace(" =  'BI-OTHER'","NOT IN ('BI-10000/20000','BI-25000/50000','BI-50000/100000','BI-100000/300000')",$limits);
	$where_statement = $where_statement." and ".$limits;
}


// compile complete query
// where statement is only program; other conditions are done in case statements to prevent reporting periods from being skipped if the reported count is zero
$sql = "
SELECT ".$sql_month.",
SUM(case when ".$sql_lag." and 
	".$where_statement." then REPORTED_COUNT end)".$sql_ec_report." as REPORTED_COUNT,
SUM(case when ".$where_statement." then ".$sql_loss." end)".$sql_ec." AS PAID_AMOUNT,
sum(case when ".$where_statement." then CWOP_COUNT end) as CWOP_COUNT,
sum(case when ".$where_statement." then OPEN_COUNT end) as OPEN_COUNT,
sum(case when ".$where_statement." then CWA_COUNT end) as CWA_COUNT,
sum(case when ".$where_statement." then BILLS_COUNT end) as BILLS_COUNT
FROM  ".($state=="FL"?"jburke":"Periscope_Data.dbo").".SEVERITY_TRIANGLES".$sql_table.$sql_county_table."
".$report_where."
group by ".$sql_group."
order by ".$sql_group."
;

";
// create array for data
$data = array();
if($state=="FL"){
	// run florida query
	$result = mysqli_query($conn,$sql);


	// for each row in result, append to $data array
	for ($x = 0; $x < mysqli_num_rows($result); $x++) {
		$data[] = mysqli_fetch_assoc($result);
	}
} else {
	// run texas query
	$rs = $conn->execute($sql);
	// get count of results fields
	$num_columns = $rs->Fields->Count();

	// loop through results fields
	for ($i=0; $i < $num_columns; $i++) {
		$arrColumns[] = $rs->Fields($i);
		$newArr[] = $rs->Fields($i)->name;
	}

	while (!$rs->EOF)  //carry on looping through while there are records
	{
		$arrRow = array();
		for($i=0; $i < $num_columns; $i++) {
			$arrRow[$newArr[$i]] = (string)$arrColumns[$i]->value;  // values converted to string, otherwise are passed through as array
		}
		$data[] = $arrRow;
		$rs->MoveNext(); //move on to the next record
	}

}
// output json version of data
echo json_encode($data);

// close connections
if($state == "FL") {
	mysqli_close($conn);
} else {
	$rs->Close();
	$conn->Close();

	$rs = null;
	$conn = null;
}
?>