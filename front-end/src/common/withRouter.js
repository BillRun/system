/**
 * react-router v3 → v6 compatibility shim.
 *
 * Wraps class (or function) components so they keep receiving the v3-style
 * router/params/location props they depend on, backed by v6 hooks internally.
 *
 *  props.router.push(path | { pathname, query, state })  – navigate
 *  props.router.replace(...)                              – replace
 *  props.router.isActive(path)                           – active-route check
 *  props.params                                          – URL params (useParams)
 *  props.location                                        – location + .query object
 *  props.routes                                          – stub array (avoids crashes)
 */
import React from 'react';
import { useNavigate, useParams, useLocation } from 'react-router-dom';

const toAbsolutePath = (path = '') => {
  if (!path || typeof path !== 'string') return path;
  if (path.startsWith('/') || path.startsWith('#') || path.startsWith('?')) {
    return path;
  }
  return `/${path}`;
};

function withRouter(Component) {
  function Wrapper(props) {
    const navigate = useNavigate();
    const params = useParams();
    const location = useLocation();

    // Parse ?key=value into an object so existing code can do location.query.foo
    const query = Object.fromEntries(new URLSearchParams(location.search));
    const locationWithQuery = { ...location, query };

    const router = {
      location: locationWithQuery,
      push(to) {
        if (typeof to === 'string') {
          navigate(toAbsolutePath(to));
        } else {
          const { pathname, query: q, state } = to;
          const search = q ? '?' + new URLSearchParams(q).toString() : '';
          navigate({ pathname: toAbsolutePath(pathname), search, state });
        }
      },
      replace(to) {
        if (typeof to === 'string') {
          navigate(toAbsolutePath(to), { replace: true });
        } else {
          const { pathname, query: q, state } = to;
          const search = q ? '?' + new URLSearchParams(q).toString() : '';
          navigate({ pathname: toAbsolutePath(pathname), search, state }, { replace: true });
        }
      },
      /** Rough isActive: true when current pathname starts with the given path */
      isActive(path) {
        if (!path) return false;
        const normalizedPath = toAbsolutePath(path);
        if (!normalizedPath) return false;
        return (
          location.pathname === normalizedPath ||
          location.pathname.startsWith(normalizedPath + '/')
        );
      },
    };

    // Stub for App.js which reads routes[last].title via this.props.routes
    const routes = [{ title: '' }];

    return (
      <Component
        {...props}
        router={router}
        params={params}
        location={locationWithQuery}
        routes={routes}
      />
    );
  }

  Wrapper.displayName = `withRouter(${Component.displayName || Component.name || 'Component'})`;
  return Wrapper;
}

export default withRouter;
