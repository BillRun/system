from core.common.helpers.discount_param_builder.base_condition_set import BaseSet


class ServiceSet(BaseSet):

    def __call__(self, *args, **kwargs):
        return self.build()

    def build(self):
        count_mark = self.determine_count_mark()
        fields = self.compose_fields()

        if count_mark == "0" and len(fields) == 1:
            final_set = {
                "subscriber": [
                    {
                        "service":
                            {
                                'any': fields
                            }
                    }
                ]
            }
        else:
            final_set = {
                "subscriber": [
                    {
                        "service":
                            {
                                count_mark: fields
                            }
                    }
                ]
            }
        return final_set


class SubscriberSet(BaseSet):

    def build(self):
        return {
            "subscriber": self.compose_fields()
        }


class CustomerSet(BaseSet):

    def build(self):
        return {
            'account': self.compose_fields()[0]
        }
