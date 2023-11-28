function addMonthsToDate(fromDate, monthsToAdd) {
    const newDate = new Date(fromDate); // Create a new Date object from the provided fromDate
  
    // Add the specified number of months to the newDate
    newDate.setMonth(newDate.getMonth() + monthsToAdd);
  
    return newDate.toISOString(); // Return the new date as an ISO string
  }

var limited_cycle_services = db.services.aggregate([{$match: {balance_period: {$exists: false}, price: {$elemMatch: {to: {$ne: "UNLIMITED"}}}}}, {$group: {_id: "$name", month_limit: {$addToSet: "$price.to"}}}, {$match: {month_limit: {$size: 1}}}, {$unwind: "$month_limit"},{$unwind: "$month_limit"}])
let today = new Date();
let lastYear = new Date(today.getFullYear() - 1, today.getMonth(), today.getDate());
let lastYearISO = lastYear.toISOString();

limited_cycle_services.forEach(service => {
    printjson("Updating subscribers with the following service: " + service._id);
    var subscribers = db.subscribers.find({'services.name': service._id, to: {$gt: ISODate(lastYearISO)}});
    subscribers.forEach(subscriber => {
        for (let i = 0; i < subscriber.services.length; i++) {
            if(subscriber.services[i].name == service._id) {
                printjson("Updating subscriber " + subscriber.sid + " with a new end date of the service to be " + service.month_limit + " after " + subscriber.services[i].from);
                subscriber.services[i].to = ISODate(addMonthsToDate(subscriber.services[i].from, service.month_limit));
            }
        }
        db.subscribers.save(subscriber);
    })
});


var services_with_revisions_with_differernt_cycles = db.services.aggregate([{$match: {balance_period: {$exists: false}, price: {$elemMatch: {to: {$ne: "UNLIMITED"}}}}}, {$group: {_id: "$name", month_limit: {$addToSet: "$price.to"}}}, {$match: {$expr: {$gt: [{$size: "$month_limit"}, 1]}}}])
services_with_revisions_with_differernt_cycles.forEach(service => {
    printjson("Service with that the month limit has been changed and will require a more complex fix: " + service._id);
});