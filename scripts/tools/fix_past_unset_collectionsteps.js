
var accounts = db.collection_steps.aggregate({$match: {"returned_value.success": {$ne: true}, trigger_date: {$lt: ISODate()}}}, {$group: {_id: "$extra_params.aid", count: {$sum: 1}}}, {$match: {count: {$gte: 1}}});
var accounts_per_day = 100; //'UNLIMITED';
var multiplier = 1;
var acc_arr =[];
var acc_array_per_day =[];

accounts.forEach(function (acc) {

acc_arr.push(acc);

});
var acc_sum = acc_arr.length;


function calcDaysOfOperation(acc_sum){
    var sum =0;
    for (var i = 1; i <=acc_sum; i=(accounts_per_day)*(multiplier)) {
        sum++;
        multiplier = multiplier*2;
        acc_array_per_day.push(i);
    }
    return sum;
}
function setAlert(stepId, cur_date, type, stepcode) {

    var data = new Date(cur_date.getTime() + type * 24 * 60 * 60000);
    print("set step " + stepcode + " to " + data);

    db.collection_steps.update({"_id": stepId}, {$set: {"trigger_date": data}});

}
function DaysToSkip(type) {

    switch (type) {
        case "HTTP step 1":
            return 1;
            break;
        case "HTTP step 2":
            return 5;
            break;
        case "HTTP step 3":
            return 10;
            break;
        case "HTTP step 4":
            return 20;
            break;
        case "HTTP step 5":
            return 30;
            break;
        case "HTTP step 6":
            return 50;
            break;
    }
}

var num_of_days = calcDaysOfOperation(acc_sum);

var pivot = 0;

for (var cur_day = 0; cur_day < num_of_days; cur_day++) {

    var day_delay = (cur_day+1);
     
    for (cur_acc=0; cur_acc < ((acc_array_per_day[cur_day])); cur_acc++) {
        if(pivot<acc_sum){
        operateOnacc(acc_arr[pivot]._id,day_delay);
        pivot++;}
    }
}

function operateOnacc(account,day_delay){
        
    print ("setting steps for account: " +account);
    var first = true;

    var currnet_acc = db.collection_steps.find({"returned_value.success": {$ne: true}, "extra_params.aid": account}).sort({step_code: 1});
    
        currnet_acc.forEach(function (step) {
            
        newDate = ISODate();
        var prevDate = new Date(newDate.getTime() + day_delay * 24 * 60 * 60000);
        var nextSkip;
        var stepcode = step["step_code"];
        var id = step["_id"];
        if (first === true) {
            nextSkip = 0;
            first = false;
        } else {
            nextSkip = DaysToSkip(stepcode);
        }
        setAlert(id, prevDate, nextSkip, stepcode);
    });
}

print("number of accounts " + acc_sum);
print("process is divided to "+num_of_days +" days");

