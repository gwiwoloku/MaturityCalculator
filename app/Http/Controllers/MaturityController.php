<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;


use Excel;
use File;
use DateTime;
use Spatie\ArrayToXml\ArrayToXml;
use Response;
use View;
use Session;



class MaturityController extends Controller
{
    /**
     * Calculate Maturity
     *
     * @param  Request  $request
     * @return Response
     */
    public function maturityHandler(Request $request)
    {
        if(!($request->hasFile('import_file'))){
            //return error
            $request->session()->flash('status', 'No file added');
            return redirect('/');

        }

        $mime = $request->file('import_file')->getMimeType();
        if($mime != "text/plain"){
            //return error
            $request->session()->flash('status', 'Please add valid CSV file');
            return redirect('/');
        }


        //get file local path
        $path = $request->file('import_file')->getRealPath();

        //parse CSV into Array
        $data = $this->ParseCsv($path,$request);

        //output xml array with calculations
        $calculatedArray=$this->calculateData($data);

        //create XML Array
        $xml = ArrayToXml::convert($calculatedArray,'policy');


        //generate current time for file name
        $today = new DateTime('now');
        $today = $today->format('YmdHis');

        //define path
        $path = storage_path().'\file'.$today.'.xml';

        //generate file on storage
        File::put($path, $xml);

        return Response::download($path);



    }


    /**
     * Parse the CSV File into a Multidimensional Array
     *
     *
     * @param $CsvLocation
     * @param  Request  $request
     * @return array
     *
     */
    public function ParseCsv($CsvLocation,Request $request){

        $data = Excel::load($CsvLocation, function($reader) {})->get();

        if(!$data){
            //return error
            $request->session()->flash('status', 'Issue parsing CSV file');
            return redirect('/');
        }
        $MaturityData = array();

        if(!empty($data) && $data->count()){
            $counter = 0;
            foreach ($data as $key => $value) {
                $MaturityData[$counter] = ['policy_number' => $value->policy_number,
                    'policy_start_date' => $value->policy_start_date,
                    'premiums' => $value->premiums,
                    'membership' => $value->membership,
                    'discretionary_bonus' => $value->discretionary_bonus,
                    'uplift_percentage' => $value->uplift_percentage,

                ];
                $counter++;
            }
        }

        if(!empty($MaturityData) ||  !(in_array(null, $MaturityData, true))){

            return $MaturityData;
        }

        dd("Error during parsing!");

    }



    /**
     * Define Policy Data
     * @return array
     */
    public function Policy(){

        //Define Policy
        $Policy= array();

        $Policy['A']= [
            'type'=>'A',
            'ManagementFee'=>'3',
            'Bonus Criteria' => '0'

        ];

        $Policy['B']= [
            'type'=>'B',
            'ManagementFee'=>'5',
            'Bonus Criteria' => '1'

        ];
        $Policy['C']= [
            'type'=>'A',
            'ManagementFee'=>'7',
            'Bonus Criteria' => '2'

        ];

        return $Policy;

    }




    /**
     * Calculate Bonus based on criteria
     * @param $data
     * @param $policyType
     * @return int
     */
    public function BonusCalculator($data,$policyType){

        $start_date= $data['policy_start_date'];
        $start_date = date_create(str_replace('/', '-',$start_date));
        $criteria_1= date_create("1990-01-01");
        $membership_right= $data['membership'];
        $criteria_2 = 'Y';
        $Bonus = 0;

        switch($policyType){

            case "A":

                if($start_date < $criteria_1){

                    $Bonus = $data['discretionary_bonus'];
                }

                break;

            case "B":

                if($membership_right == $criteria_2){

                    $Bonus = $data['discretionary_bonus'];
                }
                break;

            case "C":

                if(($start_date >= $criteria_1) && ($membership_right == $criteria_2)){

                    $Bonus = $data['discretionary_bonus'];
                }

                break;

        }

        return $Bonus;
    }


    /**
 * Calculate Bonus based on criteria
 * @param $bonusCriteria
 * @return array
 */
    public function calculateData($data){

        //load policy
        $policy = $this->policy();
        $calculatedArray = array();

        foreach($data as $key=>$value){

            //Maturity data calculations
            $policyType = substr($value['policy_number'], 0, 1);
            $managementFee = $policy[$policyType]['ManagementFee'];
            $Bonus = $this->BonusCalculator($value,$policyType);
            $upliftValue = 1 + ($value['uplift_percentage']/100);

            //calculate maturity value
            $maturityValue = (($value['premiums'] - ($value['premiums'] *$managementFee /100)) + $Bonus )*$upliftValue;
            $maturityValue = round($maturityValue, 2);
            $calculatedArray[$value['policy_number']] = [
                'policy_number' => $value['policy_number'],
                'maturity_value' =>  $maturityValue

            ];
        }

        return $calculatedArray;
    }



}