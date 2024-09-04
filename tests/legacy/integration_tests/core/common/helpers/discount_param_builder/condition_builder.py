class Condition:

    def __init__(self, field, operator, *value):
        self.field = field
        self.operator = operator
        self.value = value

    def __call__(self, *args, **kwargs):
        return {
            "field": self.field,
            "op": self.operator,
            "value": self._get_value()
        }

    def _get_value(self):
        if self.field == 'name' and self.operator in ['in', 'nin']:
            return [*self.value]
        else:
            if len(self.value) > 1:
                raise IndexError(f' Should be only 1 value for {self.operator} operator')
            return self.value[0]
