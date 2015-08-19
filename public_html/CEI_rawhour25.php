<?php
error_reporting(E_ALL);
include ("Functions1_CEI.php");
include ("Functions2_CEI.php");
include ("stats25_connect.php");
////////////////get the v25 files from directory
ini_set('max_execution_time', 5000);

 foreach(glob("/home/steveaplin/public_html/myfolder/*xml") as $filename)
{
 	$xmlname = basename($filename);
 	$fdate = getdatafromfilename($xmlname);
	$fversion = getdatafromfilename($xmlname,1);
 		echo "<pre>"; 
 		echo "hey $xmlname&#09;$fdate&#09;$fversion";
 		echo "</pre>";
   $xml_file = simplexml_load_file($filename) or die("no data loaded");
   $rr = object2array($xml_file);
	$generated_date = trim($rr['IMODocHeader']['CreatedAt']);
	$generated_date = str_replace("T"," ",$generated_date);

///////////  Database Settings  //////////////
	$cxn = mysqli_connect($db_hostname,$db_username,$db_password) or die ("Could not connect: " . mysqli_error($cxn));
			mysqli_select_db($cxn, $db_name);

///////////  put admin data from each v25 into sourcinfo25  //////////////
			
		$source25_query = "INSERT IGNORE into sourceinfo25 (xmlname, filedate, generated)
	VALUES ('$xmlname', '$fdate','$generated_date')";
		$source25_query_result = mysqli_query($cxn, $source25_query) or die ("source25_query failed, because of this: " . mysqli_error());
		$lastS25id = mysqli_insert_id($cxn);
		
///////////  begin looping script, process numbers for performance25  //////////////

		foreach ($rr['IMODocBody']['Generators'] as $generators) {$number_of_generators = count($generators);}
		foreach ($rr['IMODocBody']['Generators']['Generator'] as $generator)
	{
		{
        $unit  = $generator['GeneratorName'];
        $carbon_factor = emission_calculate($unit);
        $fuel  = $generator['FuelType'];

        // Loop over the Outputs array, get the array index for each output array.
        // Use the index later to reference the Capabilities array, which has same structure as Outputs
        foreach($generator['Outputs']['Output'] as $index => $output)
			{
            $hour = $output['Hour'];
            if (array_key_exists('EnergyMW',$output)) {$electricity = $output['EnergyMW'];} else $electricity = 0;

            // get the corresponding capability for current output
            $potentialelectricity = $generator['Capabilities']['Capability'][$index]['EnergyMW'];
            
 				if (($electricity != 0) && ($potentialelectricity != 0)){
							$capabilityfactor = $electricity/$potentialelectricity;}
						else
							$capabilityfactor = 0;
							$carbon_emissions = $carbon_factor*$electricity/1000;
 		echo "<pre>"; 
 		echo "ho $lastS25id";
 		echo "</pre>";	
//////////put each v25's outputcapability and emissions data into performance25
	$output_query = "INSERT IGNORE into performance25(xmlname, source25id, unitname, Fuel, hour, output, capability, capabilityfactor, emissions)
		VALUES ('$xmlname', '$lastS25id', '$unit', '$fuel', '$hour', '$electricity', '$potentialelectricity', '$capabilityfactor', '$carbon_emissions')";
		$output_query_result = mysqli_query($cxn, $output_query) or die ("output_query failed, and because of this: " . mysqli_error($cxn));
			}
		}
	}
}
//////// query to reclassify Lennox's output from the "gas" category to "Oil & Gas"
$oilgas_query = "update performance25 set Fuel = 'Oil & Gas' where unitname like 'Lennox%'";
$lennox_exec = mysqli_query($cxn, $oilgas_query) or die ("lennox_query failed: " . mysqli_error($cxn));
?>