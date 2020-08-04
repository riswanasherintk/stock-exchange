<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\country;
use App\company;
use App\categories;
use App\company_details;
use App\budget_logs;
class BidController extends Controller
{
    // ############# Fetch Countries ###############################
    public function get_countries()
    {
        $country=country::select('id','country_code')->get();
        if(count($country)>0){
             return response()->json([
                        'response'  => 'success', 
                        'country'     => $country
                    ]);
        }
        else{
            return response()->json([
                        'response'  => 'Failed', 
                        'msg'     => 'No Countries Found'
                    ]);
        }
    }
    ################# Fetch Categories ##############################
    public function get_categories(){
        $categories=categories::select('id','category_name')->get();
        if(count($categories)>0){
             return response()->json([
                        'response'  => 'success', 
                        'categories'     => $categories
                    ]);
        }
        else{
            return response()->json([
                        'response'  => 'Failed', 
                        'msg'     => 'No Categories Found'
                    ]);
        }
    }
    // ###################### Trade Function ##########################
    public function trade(Request $request){
        $rules = array(
            'country_id' => 'required',
            'category_id' => 'required',
            'bid' => 'required',
            'user_id' => 'required',
        );
        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
          return response()->json(array("status" => "406",
            "error" => $validator->errors(), "message" => "Validation Error"));
        }
        else
        {
            $country_id=$request->country_id;
            $category_id=$request->category_id;
            $bid=$request->bid;
            $user_id=$request->user_id;
            $company=company_details::join('companies','companies.id','company_details.company_id')
                                    ->where('category_id',$category_id)
                                    ->where('country_id',$country_id)
                                    ->select('company_id','company_name','bid_in_cents','budget_in_cents')
                                    ->orderBy('bid_in_cents','ASC')
                                    ->get();
            $winner="";                        
            if(count($company)>0){
                foreach ($company as $key => $value) {
                    $difference=$value->bid_in_cents-$bid;
                    if($difference>0){
                        $winner=$value;
                        break;
                    }

                }
                if($winner==""){
                    return response()->json([
                        'response'  => 'Failed', 
                        'msg'     => 'No Companies Passed From BaseBid Check'
                    ]);
                }
                else{
                    if($winner->budget_in_cents<$winner->bid_in_cents)
                    {
                      return response()->json([
                        'response'  => 'Failed', 
                        'msg'     => 'No Companies Passed From Budget'
                        ]);  
                    }
                    else{
                        $current_budget=$winner->budget_in_cents-$bid;
                        budget_logs::insert([
                            'company_id'=>$winner->company_id,
                            'country_id'=>$country_id,
                            'category_id'=>$category_id,
                            'user_id'=>$user_id,
                            'current_budget'=>$current_budget,
                            'previous_budget'=>$winner->budget_in_cents,
                        ]);
                        company::where('id',$winner->company_id)->update([
                            'budget_in_cents'=>$current_budget
                        ]);
                        return response()->json([
                        'response'  => 'success', 
                        'company_name'     => $winner->company_name
                        ]); 
                    }    
                }
            }
            else{
              return response()->json([
                        'response'  => 'Failed', 
                        'msg'     => 'No Companies Passed From Targeting'
                    ]);  
            }
        }
    }
}
 