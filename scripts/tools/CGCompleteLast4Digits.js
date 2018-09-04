//BRCD-1324 - Save CreditGuard last 4 digits in the account active payment gateway field
db.subscribers.find({type:"account", 'payment_gateway.active.name':"CreditGuard"}).forEach(
		function(obj) {
			var activeGateway = obj.payment_gateway.active;
			var token = activeGateway.card_token;
			var fourDigits = token.substring(token.length - 4, token.length);
			activeGateway.four_digits = fourDigits;
			db.subscribers.save(obj)
		}
)