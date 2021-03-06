<?php

function importcalc($dir,$processitem)
{
    if (!isset($processitem->consumption)) return false;
    if (!isset($processitem->generation)) return false;
    if (!isset($processitem->output)) return false;
    
    $feedA = $processitem->consumption;
    $feedB = $processitem->generation;
    $output = $processitem->output;
    // --------------------------------------------------
    
    if (!file_exists($dir.$feedA.".meta")) {
        print "input file $feedA.meta does not exist\n";
        return false;
    }

    if (!file_exists($dir.$feedB.".meta")) {
        print "input file $feedB.meta does not exist\n";
        return false;
    }
    
    if (!file_exists($dir.$output.".meta")) {
        print "output file $output.meta does not exist\n";
        return false;
    }

    $feedA_meta = getmeta($dir,$feedA);
    $feedB_meta = getmeta($dir,$feedB);
    
    if ($feedA_meta->interval != $feedB_meta->interval) {
        print "NOTICE: interval of feeds do not match, feedA:$feedA_meta->interval, feedB:$feedB_meta->interval\n";
    }
    
    print "feedA start_time=$feedA_meta->start_time interval=$feedA_meta->interval\n";
    print "feedB start_time=$feedB_meta->start_time interval=$feedB_meta->interval\n";
    
    $feedA_interval_selected = false;
    $feedB_interval_selected = false;
    if ($feedA_meta->interval==$feedB_meta->interval) $out_interval = $feedA_meta->interval;
    if ($feedA_meta->interval>$feedB_meta->interval) { $out_interval = $feedA_meta->interval; $feedA_interval_selected = true; } 
    if ($feedA_meta->interval<$feedB_meta->interval) { $out_interval = $feedB_meta->interval; $feedB_interval_selected = true; } 
    
    $out_start_time = 0;
    if ($feedA_meta->start_time==$feedB_meta->start_time) $out_start_time = (int) $feedA_meta->start_time;
    if ($feedA_meta->start_time<$feedB_meta->start_time) $out_start_time = (int) $feedA_meta->start_time;
    if ($feedA_meta->start_time>$feedB_meta->start_time) $out_start_time = (int) $feedB_meta->start_time;
    
    $out_start_time = floor($out_start_time / $out_interval) * $out_interval;
    
    $out_meta = new stdClass();
    $out_meta->start_time = $out_start_time;
    $out_meta->interval = $out_interval;
    
    print "OUT start_time=$out_start_time interval=$out_interval\n";
    
    createmeta($dir,$output,$out_meta);
    
    $output_meta = getmeta($dir,$output);

    if (!$feedA_fh = @fopen($dir.$feedA.".dat", 'rb')) {
        echo "ERROR: could not open $dir $feedA.dat\n";
        return false;
    }
    
    if (!$feedB_fh = @fopen($dir.$feedB.".dat", 'rb')) {
        echo "ERROR: could not open $dir $feedB.dat\n";
        return false;
    }
    
    if (!$output_fh = @fopen($dir.$output.".dat", 'ab')) {
        echo "ERROR: could not open $dir $output.dat\n";
        return false;
    }
    
    // Work out start and end time of merged feeds:
    $feedA_end_time = $feedA_meta->start_time + ($feedA_meta->interval * $feedA_meta->npoints);
    $feedB_end_time = $feedB_meta->start_time + ($feedB_meta->interval * $feedB_meta->npoints);
    
    $start_time = $output_meta->start_time + ($output_meta->npoints * $output_meta->interval);
    $end_time = $feedA_end_time;
    if ($feedB_end_time>$feedA_end_time) $end_time = $feedB_end_time;
    
    $interval = $output_meta->interval;
    
    $buffer = "";
    for ($time=$start_time; $time<$end_time; $time+=$interval) 
    {
        $posA = floor(($time - $feedA_meta->start_time) / $feedA_meta->interval);
        $posB = floor(($time - $feedB_meta->start_time) / $feedB_meta->interval);
    
        $useval = NAN;
        $genval = NAN;
    
        if ($posA>=0 && $posA<$feedA_meta->npoints) {
            fseek($feedA_fh,$posA*4);
            $feedA_tmp = unpack("f",fread($feedA_fh,4));
            $useval = $feedA_tmp[1];
        }

        if ($posB>=0 && $posB<$feedB_meta->npoints) {
            fseek($feedB_fh,$posB*4);
            $feedB_tmp = unpack("f",fread($feedB_fh,4));
            $genval = $feedB_tmp[1];
        }
        
        $importval = NAN;
        if (!is_nan($useval)) $importval = $useval;
        if (!is_nan($genval) && !is_nan($genval)) $importval = $useval - $genval;
        if ($importval<0) $importval = 0;
        
        $buffer .= pack("f",$importval*1.0);
    }
     
    fwrite($output_fh,$buffer);
    
    $byteswritten = strlen($buffer);
    print "bytes written: ".$byteswritten."\n";
    fclose($output_fh);
    fclose($feedA_fh);
    fclose($feedB_fh);
    
    if ($byteswritten>0) {
        print "last time value: ".$time." ".$importval."\n";
        updatetimevalue($output,$time,$importval);
    }
    return true;
}
