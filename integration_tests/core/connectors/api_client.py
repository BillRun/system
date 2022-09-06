import requests

from config.env import ENV
from core.common.logger import LOGGER
from core.common.utils import api_logger


class APIClient:
    _session = requests.Session()

    def __init__(self, username=None, password=None):
        self._username = username
        self._password = password
        self._env = ENV

        if not self._session.cookies:
            self._log_in()

    def _log_in(self):
        data = {
            "username": self._username,
            "password": self._password
        }
        response = self._session.post(self._url("api/auth"), data=data)
        LOGGER.info(f'{response.request.method}: {response.request.url}')

        if response.json()['details'] is False:
            raise Exception(
                f'Login is unsuccessful \n details: {response.json()}'
            )
        LOGGER.info('Login is successful')

    def reset_cookie(self):
        self._log_in()

    def _url(self, path):
        if 'localhost' in self._env:
            scheme = 'http'
        else:
            scheme = 'https'

        return f"{scheme}://{self._env}/{path}"

    @api_logger
    def get(self, path, *args, **kwargs):
        return self._session.get(self._url(path), *args, **kwargs)

    @api_logger
    def post(self, path, **kwargs):
        return self._session.post(self._url(path), **kwargs)

    @api_logger
    def put(self, path, **kwargs):
        return self._session.put(self._url(path), **kwargs)

    @api_logger
    def patch(self, path, **kwargs):
        return self._session.patch(self._url(path), **kwargs)

    @api_logger
    def delete(self, path,  **kwargs):
        return self._session.delete(self._url(path), **kwargs)





