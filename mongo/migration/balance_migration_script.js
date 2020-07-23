const time = ISODate();
const services = getServices();

services.forEach(function (service) {
	const groups = getServiceGroups(service);
	groups.forEach(function (group) {
		const balances = getBalances(group);
		balances.forEach(function (balance) {
			// update/create add-on specific balance
			db.balances.update(
							{
								aid: balance['aid'],
								sid: balance['sid'],
								from: balance['from'],
								to: balance['to'],
								period: balance['period'],
								service_name: service['name'],
								connection_type: 'postpaid',
								priority: {
									$exists: true,
									$ne: 0
								}
							},
							{
								$setOnInsert: {
									aid: balance['aid'],
									sid: balance['sid'],
									from: balance['from'],
									to: balance['to'],
									period: balance['period'],
									start_period: balance['start_period'],
									connection_type: balance['connection_type'],
									current_plan: balance['current_plan'],
									plan_description: balance['plan_description'],
									priority: service['service_id'] || Math.floor(Math.random() * 10000000000000000) + 1,
									service_name: service['name'],
									added_by_script: ISODate(),
									['balance.groups.' + group['name'] + '.total']: balance['balance']['groups'][group['name']]['total'],
									['balance.totals.' + group['usaget'] + '.cost']: 0
								},
								$inc: {
									['balance.groups.' + group['name'] + '.count']: balance['balance']['groups'][group['name']]['count'],
									['balance.groups.' + group['name'] + '.usagev']: balance['balance']['groups'][group['name']]['usagev'],
									['balance.totals.' + group['usaget'] + '.usagev']: balance['balance']['groups'][group['name']]['usagev'],
									['balance.totals.' + group['usaget'] + '.count']: balance['balance']['groups'][group['name']]['count']
								},
								$set: {
									['balance.groups.' + group['name'] + '.left']: balance['balance']['groups'][group['name']]['left']
								}
							},
							{
								upsert: true
							}
			);

			// update balance totals
			balance['balance']['totals'][group['usaget']]['usagev'] -= balance['balance']['groups'][group['name']]['usagev'];
			balance['balance']['totals'][group['usaget']]['count'] -= balance['balance']['groups'][group['name']]['count'];

			// remove group from monthly balance
			delete balance['balance']['groups'][group['name']];
			if (typeof Object.keys(balance['balance']['groups'])[0] == 'undefined') {
				delete balance['balance']['groups'];
			}
			balance['updated_by_script'] = ISODate();
			db.balances.save(balance);
		});
	});

});

// get services aligned to cycle that are not included in any plan
function getServices() {
	const ret = [];
	const alignedToCycleServices = db.services.find({
		from: {$lte: time},
		to: {$gt: time},
		$or: [
			{balance_period: {$exists: false}},
			{balance_period: 'default'}
		]
	});
	var servicesIncludedInPlans = db.plans.aggregate([
		{
			$match: {
				from: {$lte: time},
				to: {$gt: time},
				'include.services': {$exists: true}
			}
		},
		{
			$unwind: '$include.services'
		},
		{
			$project: {
				_id: 0,
				service: '$include.services'
			}
		}
	]);

	const servicesIncludedInPlansNames = servicesIncludedInPlans.map(x => x['service']);
	alignedToCycleServices.forEach(function (service) {
		if (servicesIncludedInPlansNames.indexOf(service['name']) == -1) {
			ret.push(service);
		}
	});

	return ret;
}

// get monthly balances with existing group
function getBalances(group) {
	const ret = db.balances.find({
		from: {$lte: time},
		to: {$gt: time},
		['balance.groups.' + group['name']]: {$exists: true},
		priority: 0
	});

	return ret;
}

function getServiceGroups(service) {
	const ret = [];
	if (typeof service['include'] == 'undefined' || typeof service['include']['groups'] == 'undefined') {
		return ret;
	}
	
	for (var group in service['include']['groups']) {
		ret.push({
			name: group,
			usaget: Object.keys(service['include']['groups'][group]['usage_types'])[0],
			value: service['include']['groups'][group]['value'],
		});
	}
	
	return ret;
}