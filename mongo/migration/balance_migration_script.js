// need to set start end dates. 

from = ISODate("2020-01-30T21:00:00Z");
to = ISODate("2020-06-30T21:00:00Z");

function isEmpty(obj) {
    for(var key in obj) {
        if(obj.hasOwnProperty(key))
            return false;
    }
    return true;
}


function setUsageData(old_count,final_left,new_total,final_usagev){
    
    new_updated_service = {
                          "count": old_count,
                          "left" : final_left,
                          "total" : new_total,
                          "usagev": final_usagev          
                        };
    return new_updated_service;
}

function setTotal(new_cost,new_count,totals_object){
    totals_object['cost'] = new_cost;
    totals_object['count'] = new_count;
    return totals_object;
}


function checkService(service_name,from,to,sid){
//check if service non-custom
var service_non_custom = db.services.find({name:service_name,balance_period:{$exists:0}}).toArray()[0];

if(!isEmpty(service_non_custom)){
    //check if not connected to subs plan 
    var sub_service_check = db.subscribers.find({sid: sid, services:{$exists:1,$ne: []}},{services:1}).toArray()[0];
    if(!isEmpty(sub_service_check)&&(sub_service_check!=="undefined")){   
    for (var i = 0; i < sub_service_check['services'].length; i++) {
       
    if((sub_service_check['services'][i]['name'] === service_name)&&(from >=sub_service_check['services'][i]['from'])&&
            (to <=sub_service_check['services'][i]['to'])){
        return true;
    }
    }
}
}
    return false;

}

function checkIfBalanceExists(service_name){
    
    location = 'balance.groups.'+[service_name]+'.left';        
    service_exists = db.balances.find({from:{$gte:from},to:{$lte:to}, service_name:service_name,
    [location]:{$gt:0}}).toArray()[0];

    // case all service usage is full, but server exists
     if (typeof service_exists === "undefined"){
    service_exists = db.balances.find({from:{$gte:from},to:{$lte:to}, service_name:service_name,
    [location]:{$eq:0}}).toArray()[0];
     }

    return service_exists;
    
}

old_balances = db.balances.find({from:{$gte:from},to:{$lte:to}, priority:{$eq:0},service_name:{$exists:0}});

if(old_balances.balance!=="undefined"){


old_balances.forEach(function (current_balance){
       
    id = current_balance._id;
    sid = current_balance.sid;
    data = current_balance['balance']['groups'];

          for ( var service_name in data) {
            is_valid = checkService(service_name,from,to,sid); 
            if(is_valid){
               
               new_balance_found = checkIfBalanceExists(service_name);
               
               if(!isEmpty(new_balance_found)){
                   print("this service name exists, updating...");
                // old service data
                    old_group_data = current_balance['balance']['groups'][service_name];
                    new_group_data = new_balance_found['balance']['groups'][service_name];
                    
                    call_or_data = null;
                //check if service is data or calls 
                    if (typeof (new_group_data['usage_types']) === "undefined") {
                        call_or_data = "call";
                    } else {
                        call_or_data = "data";
                    }
                    
                    
                    old_count = old_group_data.count;
                    old_left = old_group_data.left;
                    old_total = old_group_data.total;
                    old_usagev =  old_group_data.usagev;
                    old_cost = current_balance['balance']['totals'][call_or_data].cost;
                  
                //new serivce data
                    new_left = new_group_data.left;
                    new_total = new_group_data.total;
                    new_usagev = new_group_data.usagev;
                    new_cost = old_cost + new_balance_found['balance']['totals'][call_or_data].cost;
                    new_count = old_count+new_group_data.count;

                //final service data
                    final_usagev = old_usagev + new_usagev;
                    
                    //set service usage data
                    final_left = new_total - final_usagev;
                    if(final_left<0){
                        final_left = 0;
                    }
                    new_updated_service = setUsageData(old_count,final_left,new_total,final_usagev);
                    //set total object
                    new_total_object = setTotal(new_cost,new_count,new_balance_found['balance']['totals'][call_or_data]);             
                    new_balance_found['balance']['groups'][service_name] = new_updated_service;
                    new_balance_found['balance']['totals'][call_or_data] = new_total_object;
                    new_balance_found['service_updated'] = true;
                    db.balances.save(new_balance_found);
                
                
                unset_old_values = 'balance.'+'groups.'+service_name;
                print(unset_old_values);
                db.balances.update(
                        { _id: id },
                        { $unset: { [unset_old_values]: "" } }
                        )                   
               }
                else{
                    //service doesn't exist
                    print("no aligned services found");
                }                                    
            }
            
            }
});
}
