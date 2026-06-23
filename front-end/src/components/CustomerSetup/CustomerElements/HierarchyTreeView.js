import React, { useState, useEffect, useLayoutEffect, useRef } from 'react';
import PropTypes from 'prop-types';
import Tree from 'react-hierarchy-tree-graph';
import { apiBillRun } from '@/common/Api';
import { getHierarchyQuery } from '@/common/ApiQueries';

const getTreeDepth = (node) => {
  if (!node || !node.children || node.children.length === 0) return 1;
  return 1 + Math.max(...node.children.map(getTreeDepth));
};

const countLeaves = (node) => {
  if (!node || !node.children || node.children.length === 0) return 1;
  return node.children.reduce((sum, child) => sum + countLeaves(child), 0);
};

// flat array → nested tree; root = node with parent_aid === null
const flatToNested = (nodes) => {
  const map = {};
  nodes.forEach(n => { map[n.aid] = { ...n, children: [] }; });

  let root = null;
  nodes.forEach(n => {
    if (n.parent_aid == null) {
      root = map[n.aid];
    } else if (map[n.parent_aid]) {
      map[n.parent_aid].children.push(map[n.aid]);
    }
  });
  return root;
};

// normalise BE response: { status, details: { "aid": { name, parent_aid } } }
const parseApiResponse = (raw) => {
  if (!raw) return null;
  if (Array.isArray(raw)) return parseApiResponse(raw[0]);
  if (raw.details && typeof raw.details === 'object' && !Array.isArray(raw.details)) {
    return Object.entries(raw.details).map(([aid, node]) => ({
      ...node,
      aid: parseInt(aid),
      _id: node._id && node._id.$id ? node._id.$id : null,
    }));
  }
  if (raw.data) return parseApiResponse(raw.data);
  return null;
};

const NODE_WIDTH = 180;
const NODE_HEIGHT = 120;
const FOREIGN_HALF_H = 25; // half of actual box height (~50px, centered at anchor)
const ZOOM_STEP = 0.15;
const ZOOM_MIN = 0.1;
const ZOOM_MAX = 3;

const zoomBtnStyle = {
  display: 'block',
  width: 28,
  height: 28,
  lineHeight: '26px',
  textAlign: 'center',
  background: '#fff',
  border: '1px solid #ccc',
  borderRadius: 4,
  cursor: 'pointer',
  fontSize: 16,
  fontWeight: 700,
  color: '#555',
  padding: 0,
  userSelect: 'none',
};

// Custom elbow path: exits bottom-center of source, enters top-center of target
const elbowPath = ({ source, target }) => {
  const sy = source.y + FOREIGN_HALF_H;
  const ty = target.y - FOREIGN_HALF_H;
  const midY = (sy + ty) / 2;
  return `M ${source.x},${sy} V ${midY} H ${target.x} V ${ty}`;
};

const toLibraryNode = (node, currentAid) => {
  const baseName = node.name || `${node.firstname || ''} ${node.lastname || ''}`.trim();
  return {
    name: baseName ? `${baseName} [${node.aid}]` : `[${node.aid}]`,
    key: String(node.aid),
    displayName: baseName,
    aid: node.aid,
    _id: node._id || null,
    is_payer: !!node.is_payer,
    issues_invoice: !!node.issues_invoice,
    isCurrent: node.aid === currentAid,
    children: (node.children || []).map(child => toLibraryNode(child, currentAid)),
  };
};

const NodeLabel = ({ nodeData }) => {
  // The library renders this element once as a template (no props) before cloning it per node.
  if (!nodeData) return null;
  const { displayName, aid, _id, isCurrent, is_payer, issues_invoice } = nodeData;
  const accountUrl = _id ? `${window.location.origin}/#/customers/customer/${_id}` : null;

  const boxStyle = {
    display: 'block',
    width: NODE_WIDTH,
    minHeight: 50,
    background: isCurrent ? '#337ab7' : '#fff',
    border: `1px solid ${isCurrent ? '#2e6da4' : '#ccc'}`,
    borderRadius: 4,
    padding: '5px 8px',
    fontSize: 11,
    boxSizing: 'border-box',
    color: isCurrent ? '#fff' : '#337ab7',
    textDecoration: 'none',
  };

  const inner = (
    <React.Fragment>
      <div style={{ position: 'relative', textAlign: 'center', minHeight: 16 }}>
        <span style={{ fontWeight: 700 }}>[{aid}]</span>
        {(is_payer || issues_invoice) && (
          <span style={{ position: 'absolute', right: 0, top: '50%', transform: 'translateY(-50%)', display: 'flex', gap: 3, alignItems: 'center' }}>
            {is_payer && (
              <span title="Payer" style={{ color: isCurrent ? '#fff' : '#5cb85c', fontWeight: 700, fontSize: 12 }}>$</span>
            )}
            {issues_invoice && (
              <i className="fa fa-file-text-o" title="Issues Invoice" style={{ color: isCurrent ? '#fff' : '#f0ad4e' }} aria-hidden="true" />
            )}
          </span>
        )}
      </div>
      {displayName && (
        <React.Fragment>
          <hr style={{ margin: '4px 0', border: 'none', borderTop: `1px solid ${isCurrent ? 'rgba(255,255,255,0.3)' : '#e0e0e0'}` }} />
          <div style={{ textAlign: 'center', wordBreak: 'break-word', lineHeight: 1.3 }}>
            {displayName}
          </div>
        </React.Fragment>
      )}
    </React.Fragment>
  );

  if (accountUrl) {
    return <a href={accountUrl} target="_blank" rel="noopener noreferrer" style={boxStyle}>{inner}</a>;
  }
  return <div style={boxStyle}>{inner}</div>;
};

// Not required: the template render above passes no props; .isRequired would warn falsely.
NodeLabel.propTypes = {
  nodeData: PropTypes.shape({
    name: PropTypes.string,
    displayName: PropTypes.string,
    aid: PropTypes.number,
    _id: PropTypes.string,
    is_payer: PropTypes.bool,
    issues_invoice: PropTypes.bool,
    isCurrent: PropTypes.bool,
  }),
};

const HierarchyTreeView = ({ rootAid, currentAid }) => {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [treeData, setTreeData] = useState(null);
  const [treeBounds, setTreeBounds] = useState(null); // { naturalW, naturalH, contentCenterX }
  const [fitScale, setFitScale] = useState(null);
  const [zoom, setZoom] = useState(1);
  const [panOffset, setPanOffset] = useState({ x: 0, y: 0 });
  const containerRef = useRef(null);
  const dragRef = useRef(null);

  const handleMouseDown = (e) => {
    // Don't interfere with zoom buttons or node links/clicks
    if (e.target.closest('button') || e.target.closest('a')) return;
    e.preventDefault();
    dragRef.current = {
      startX: e.clientX,
      startY: e.clientY,
      startPanX: panOffset.x,
      startPanY: panOffset.y,
    };
    if (containerRef.current) containerRef.current.style.cursor = 'grabbing';
  };

  const handleMouseMove = (e) => {
    if (!dragRef.current) return;
    setPanOffset({
      x: dragRef.current.startPanX + (e.clientX - dragRef.current.startX),
      y: dragRef.current.startPanY + (e.clientY - dragRef.current.startY),
    });
  };

  const handleMouseUp = () => {
    if (!dragRef.current) return;
    dragRef.current = null;
    if (containerRef.current) containerRef.current.style.cursor = 'grab';
  };

  // Ctrl+scroll / pinch-to-zoom; non-passive so we can preventDefault. The container is always
  // mounted, so this attaches once.
  useEffect(() => {
    const el = containerRef.current;
    if (!el) return undefined;
    const onWheel = (e) => {
      if (!e.ctrlKey && !e.metaKey) return;
      e.preventDefault();
      const delta = -e.deltaY * 0.005;
      setZoom(z => Math.min(Math.max(parseFloat((z + delta).toFixed(2)), ZOOM_MIN), ZOOM_MAX));
    };
    el.addEventListener('wheel', onWheel, { passive: false });
    return () => el.removeEventListener('wheel', onWheel);
  }, []);

  // Compute the fit-to-window scale from the rendered DOM. The container is always mounted, so
  // containerRef is never null and fitScale can't get stuck — measurement refines the view, it
  // never gates visibility, so a slow/failed measure just leaves the tree at its initial scale.
  useLayoutEffect(() => {
    if (!treeData || treeBounds) return undefined;

    // True once d3 has laid out positioned nodes we can measure.
    const tryMeasure = () => {
      const container = containerRef.current;
      if (!container) return false;
      const svgG = container.querySelector('svg > g');
      if (!svgG) return false;
      const mainTxMatch = (svgG.getAttribute('transform') || '').match(/translate\(([^,]+)/);
      if (!mainTxMatch) return false;

      const rootX = parseFloat(mainTxMatch[1]);
      let minX = Infinity;
      let maxX = -Infinity;
      let maxRelY = -Infinity;
      svgG.querySelectorAll(':scope > g[transform]').forEach((g) => {
        const m = (g.getAttribute('transform') || '').match(/translate\(([^,]+),\s*([^)]+)\)/);
        if (!m) return;
        const x = rootX + parseFloat(m[1]);
        minX = Math.min(minX, x - NODE_WIDTH / 2);
        maxX = Math.max(maxX, x + NODE_WIDTH / 2);
        maxRelY = Math.max(maxRelY, parseFloat(m[2]));
      });
      if (minX === Infinity) return false; // nodes not laid out yet

      const containerW = container.offsetWidth || 900;
      const containerH = container.offsetHeight || 600;
      const contentW = maxX - minX + 40;
      // translate.y=60 + deepest relative y + half node height + small padding
      const contentH = 60 + maxRelY + FOREIGN_HALF_H + 30;
      const contentCenterX = (minX + maxX) / 2;
      // Fit entire tree with 5% breathing room; never magnify beyond 1:1
      const computed = Math.min(containerW / contentW, containerH / contentH, 1) * 0.95;
      const naturalW = Math.max(containerW, countLeaves(treeData[0]) * (NODE_WIDTH + 20) * 2);

      setTreeBounds({ naturalW, naturalH: contentH, contentCenterX });
      setFitScale(computed);
      setZoom(computed);
      return true;
    };

    // Fast path: already laid out — measure before paint, no flash.
    if (tryMeasure()) return undefined;

    // Slow path: d3 lays out over a few frames; poll until nodes appear, bounded.
    let rafId;
    let frames = 0;
    const MAX_FRAMES = 60; // ~1s at 60fps
    const poll = () => {
      frames += 1;
      if (tryMeasure() || frames >= MAX_FRAMES) return;
      rafId = requestAnimationFrame(poll);
    };
    rafId = requestAnimationFrame(poll);
    return () => cancelAnimationFrame(rafId);
  }, [treeData, treeBounds]);

  useEffect(() => {
    if (rootAid == null) return undefined;
    let cancelled = false;
    const query = getHierarchyQuery(rootAid);
    setLoading(true);
    setError(null);
    setTreeData(null);
    setTreeBounds(null);
    setFitScale(null);
    setZoom(1);
    setPanOffset({ x: 0, y: 0 });
    apiBillRun(query)
      .then((response) => {
        if (cancelled) return;
        const nodes = parseApiResponse(response.data[0]);
        const nested = nodes ? flatToNested(nodes) : null;
        if (nested) setTreeData([toLibraryNode(nested, currentAid)]);
        setLoading(false);
      })
      .catch((err) => {
        if (cancelled) return;
        const apiErr = err && err.error && err.error[0] && err.error[0].error;
        const apiMsg = (apiErr && (apiErr.desc || apiErr.message || (apiErr.data && apiErr.data.message))) || null;
        setError(apiMsg || err.message || 'Failed to load hierarchy');
        setLoading(false);
      });
    return () => { cancelled = true; };
  }, [rootAid, currentAid]);

  // Recomputed each render; before measurement lands it falls back to node-count estimates.
  const hasTree = !!treeData;
  const nodeSpacingX = NODE_WIDTH + 20;
  const leaves = hasTree ? countLeaves(treeData[0]) : 1;
  const depth = hasTree ? getTreeDepth(treeData[0]) : 1;
  const containerW = containerRef.current ? containerRef.current.offsetWidth : 900;
  const containerH = containerRef.current ? containerRef.current.offsetHeight : 600;

  const naturalW = treeBounds ? treeBounds.naturalW : Math.max(containerW, leaves * nodeSpacingX * 2);
  const naturalH = treeBounds ? treeBounds.naturalH : Math.max(containerH, depth * NODE_HEIGHT + 100);
  const translateX = naturalW / 2; // root always at horizontal center of canvas

  // Center by content bounds (not by root), so asymmetric trees don't clip on either side.
  const contentCenterX = treeBounds ? treeBounds.contentCenterX : naturalW / 2;
  const canvasLeft = containerW / 2 - contentCenterX * zoom;
  const canvasTop = Math.max(0, (containerH - naturalH * zoom) / 2);

  // Centered overlay for the loading / error / empty states.
  const overlayStyle = {
    position: 'absolute',
    top: 0,
    right: 0,
    bottom: 0,
    left: 0,
    display: 'flex',
    alignItems: 'center',
    justifyContent: 'center',
    textAlign: 'center',
    padding: 24,
  };

  return (
    <div
      ref={containerRef}
      style={{
        width: '100%',
        height: 'calc(80vh - 130px)',
        minHeight: 400,
        position: 'relative',
        overflow: 'hidden',
        background: '#fff',
        cursor: hasTree ? 'grab' : 'default',
      }}
      onMouseDown={handleMouseDown}
      onMouseMove={handleMouseMove}
      onMouseUp={handleMouseUp}
      onMouseLeave={handleMouseUp}
    >
      {loading && (
        <div style={overlayStyle}>
          <i className="fa fa-spinner fa-spin fa-2x" aria-hidden="true" />
        </div>
      )}

      {!loading && error && (
        <div style={{ ...overlayStyle, color: '#a94442' }}>
          <span>
            <i className="fa fa-exclamation-circle" aria-hidden="true" />
            {' '}{error}
          </span>
        </div>
      )}

      {!loading && !error && !hasTree && (
        <div style={{ ...overlayStyle, color: '#777' }}>
          No hierarchy data available.
        </div>
      )}

      {!loading && !error && hasTree && (
        <React.Fragment>
          <div style={{ position: 'absolute', top: 8, right: 8, zIndex: 10, display: 'flex', flexDirection: 'column', gap: 4 }}>
            <button
              style={zoomBtnStyle}
              title="Zoom in"
              onClick={() => setZoom(z => Math.min(parseFloat((z + ZOOM_STEP).toFixed(2)), ZOOM_MAX))}
            >+</button>
            <button
              style={{ ...zoomBtnStyle, fontSize: 13 }}
              title="Fit to window"
              onClick={() => { setZoom(fitScale !== null ? fitScale : 1); setPanOffset({ x: 0, y: 0 }); }}
            >⊡</button>
            <button
              style={zoomBtnStyle}
              title="Zoom out"
              onClick={() => setZoom(z => Math.max(parseFloat((z - ZOOM_STEP).toFixed(2)), ZOOM_MIN))}
            >−</button>
          </div>

          {/* Tree canvas: zoom via CSS transform, pan via panOffset */}
          <div
            style={{
              position: 'absolute',
              left: canvasLeft + panOffset.x,
              top: canvasTop + panOffset.y,
              width: naturalW,
              height: naturalH,
              transform: `scale(${zoom})`,
              transformOrigin: 'top left',
            }}
          >
            <Tree
              data={treeData}
              orientation="vertical"
              translate={{ x: translateX, y: 60 }}
              nodeSize={{ x: nodeSpacingX, y: NODE_HEIGHT }}
              separation={{ siblings: 1.1, nonSiblings: 1.3 }}
              transitionDuration={0}
              collapsible={false}
              pathFunc={elbowPath}
              zoomable={false}
              allowForeignObjects
              nodeSvgShape={{ shape: 'none' }}
              nodeLabelComponent={{
                render: <NodeLabel />,
                foreignObjectWrapper: {
                  width: NODE_WIDTH,
                  height: 70,
                  x: -(NODE_WIDTH / 2),
                  y: -25,
                },
              }}
            />
          </div>
        </React.Fragment>
      )}
    </div>
  );
};

HierarchyTreeView.propTypes = {
  rootAid: PropTypes.number,
  currentAid: PropTypes.number,
};

HierarchyTreeView.defaultProps = {
  rootAid: null,
  currentAid: null,
};

export default HierarchyTreeView;
