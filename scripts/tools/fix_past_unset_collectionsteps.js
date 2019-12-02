
var accounts = db.collection_steps.aggregate({$match: {"returned_value.success": {$ne: true}, trigger_date: {$lt: ISODate()}}}, {$group: {_id: "$extra_params.aid", count: {$sum: 1}}}, {$match: {count: {$gte: 1}}});
var accounts_per_day = 1500; //'UNLIMITED';
var multiplier = 1;
var acc_arr = [];
var acc_array_per_day = [];

accounts.forEach(function (acc) {
    acc_arr.push(acc);
});

var acc_sum = acc_arr.length;


function calcPiv(steptype) {
    if (steptype === "HTTP step 1") {
        return 0;
    } else {
        return DaysToSkip(steptype);
    }
}


function calcDaysOfOperation(acc_sum) {
    var sum = 0;
    for (var i = 1; i <= acc_sum; i = i + ((accounts_per_day) * (multiplier))) {
        sum++;
    }
    return sum;
}

function arrCreator(num_days, mul, acc_per_day) {
    total = mul * acc_per_day;
    acc_amounts = acc_sum;
    for (var i = 0; i < num_days; i++) {
        for (var j = 0; i < total; i++) {
            if (total > acc_amounts) {
                acc_array_per_day.push(acc_amounts);
            } else {
                acc_array_per_day.push(total);
                acc_amounts = acc_amounts - total;
            }
        }
    }
}

var num_of_days = calcDaysOfOperation(acc_sum);

arrCreator(num_of_days, multiplier, accounts_per_day);

function setAlert(stepId, cur_date, type, stepcode, pivot) {

    var data = new Date(cur_date.getTime() + ((type - pivot) * 24 * 60 * 60000));
    print("set step " + stepcode + " to " + data);
    print("");

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


var pivot = 0;

for (var cur_day = 0; cur_day < num_of_days; cur_day++) {

    var day_delay = (cur_day);


    for (cur_acc = 0; cur_acc < ((acc_array_per_day[cur_day])); cur_acc++) {
        if (pivot < acc_sum) {
            operateOnacc(acc_arr[pivot]._id, day_delay);
            pivot++;
        }
    }
}

function operateOnacc(account, day_delay) {
    
    print("setting steps for account: " + account);

    var currnet_acc = db.collection_steps.find({"returned_value.success": {$ne: true}, "extra_params.aid": account}).sort({step_code: 1});
    var first = true;
    var pivot;
    var flag = false;

    currnet_acc.forEach(function (step) {
        
        newDate = ISODate();
        var prevDate = new Date(newDate.getTime() + day_delay * 24 * 60 * 60000);
        var nextSkip;
        var stepcode = step["step_code"];
        var id = step["_id"];
        if (first === true) {
            nextSkip = 0;
            var prevDate = new Date(newDate.getTime() + day_delay * 24 * 60 * 60000);
            first = false;
            pivot = 0;
            if (stepcode !== "HTTP step 1") {
                flag = true;
            }
        } else {
            nextSkip = DaysToSkip(stepcode);
        }
        setAlert(id, prevDate, nextSkip, stepcode, pivot);
        if (flag) {
            pivot = calcPiv(stepcode);
            flag = false;
        }
    });
}

print("number of accounts " + acc_sum);
print("process is divided to " + num_of_days + " days");





