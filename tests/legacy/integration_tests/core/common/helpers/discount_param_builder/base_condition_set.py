from abc import ABC, abstractmethod

from core.common.helpers.discount_param_builder.condition_builder import Condition


class BaseSet(ABC):

    def __init__(self, **kwargs: list[Condition]):
        """
        kwargs: cond_set1=[Condition, ...], cond_set2=[Condition, ...]
        """
        self.kwargs = kwargs

    def __call__(self, *args, **kwargs):
        return self.build()

    @abstractmethod
    def build(self):
        pass

    def determine_count_mark(self):
        return 'any' if list(filter(lambda x: len(self.kwargs[x]) > 1, self.kwargs)) else "0"

    def compose_fields(self):
        return [
            {'fields': [cond() for cond in conditions]} for conditions in self.kwargs.values()
        ]
