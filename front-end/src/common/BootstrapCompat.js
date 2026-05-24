/**
 * Bootstrap 3 → Bootstrap 5 / react-bootstrap v0.33 → v2 compatibility shim.
 * Provides wrappers for removed/renamed components so existing JSX doesn't need to change.
 */
import React, { useState, useRef, useEffect } from 'react';
import classNames from 'classnames';
import { Card, Form, Container, Collapse } from 'react-bootstrap';
// ---------------------------------------------------------------------------
// Panel  →  Card  (supports: header, bsStyle, collapsible, expanded props)
// ---------------------------------------------------------------------------
export const Panel = ({
  children,
  header,
  bsStyle,
  collapsible,
  expanded,
  defaultExpanded,
  className = '',
  style,
  ...rest
}) => {
  const borderVariant = bsStyle && bsStyle !== 'default' ? `border-${bsStyle}` : '';
  const panelStyle = bsStyle && bsStyle !== 'default' ? `panel-${bsStyle}` : 'panel-default';
  const panelClassName = `panel ${panelStyle} ${borderVariant} ${className}`.trim();
  // BS3 react-bootstrap 0.31: collapsible Panel defaults to closed (defaultExpanded = false).
  // Match that behaviour: undefined → false (closed), true → true (open).
  const [internalExpanded, setInternalExpanded] = useState(defaultExpanded ?? false);
  const isControlled = typeof expanded === 'boolean';
  const isExpanded = isControlled ? expanded : internalExpanded;
  // Mirrors RB 0.31.5 Panel.renderHeader: React elements get panel-title class cloned in
  // (h1-h6 also get clearfix for floated buttons); primitives render as-is (no h3 wrapper).
  const isHeadingTag = node =>
    React.isValidElement(node)
    && typeof node.type === 'string'
    && /^h[1-6]$/.test(node.type);

  const normalizeHeaderNode = (headerValue) => {
    if (React.isValidElement(headerValue)) {
      return React.cloneElement(headerValue, {
        className: classNames(
          headerValue.props.className,
          'panel-title',
          isHeadingTag(headerValue) && 'clearfix',
        ),
      });
    }
    return headerValue;
  };

  if (collapsible) {
    // CSS in index.css targets: .panel.collapsible .panel-heading .panel-title > a[.collapsed]
    // Wrap header with panel-title + anchor so legacy arrows/spacing continue to work.
    const headerContent = React.isValidElement(header) && typeof header.type === 'string' && /^h[1-6]$/.test(header.type)
      ? header.props.children
      : header;
    const onToggle = (e) => {
      e.preventDefault();
      if (!isControlled) {
        setInternalExpanded(!isExpanded);
      }
    };
    const collapsibleHeader = (
      <h3 className="panel-title">
        <a
          href="#"
          role="button"
          className={isExpanded ? '' : 'collapsed'}
          onClick={onToggle}
        >
          {headerContent}
        </a>
      </h3>
    );
    return (
      <Card className={`${panelClassName} collapsible`} style={style}>
        {header && <div className="panel-heading">{collapsibleHeader}</div>}
        <Collapse in={isExpanded}>
          <div>
            <div className="panel-body">{children}</div>
          </div>
        </Collapse>
      </Card>
    );
  }

  const headerNode = normalizeHeaderNode(header);

  // Match react-bootstrap 0.31.5 Panel.renderBody: when there are no body
  // children, the body <div class="panel-body"> is NOT rendered at all
  // (avoid showing an empty 30px white strip below the heading).
  const bodyArray = React.Children.toArray(children);
  const hasBody = bodyArray.length > 0;

  return (
    <Card className={panelClassName} style={style} {...rest}>
      {header && <div className="panel-heading">{headerNode}</div>}
      {hasBody && <div className="panel-body">{bodyArray}</div>}
    </Card>
  );
};

// ---------------------------------------------------------------------------
// ControlLabel  →  Form.Label  (adds control-label for .form-horizontal layouts)
// ---------------------------------------------------------------------------
export const ControlLabel = ({ children, className = '', ...props }) => (
  <Form.Label className={`control-label ${className}`.trim()} {...props}>{children}</Form.Label>
);

// ---------------------------------------------------------------------------
// HelpBlock  →  Form.Text  (adds help-block for project CSS selectors)
// ---------------------------------------------------------------------------
export const HelpBlock = ({ children, className = '', ...props }) => (
  <Form.Text className={`help-block text-muted ${className}`.trim()} {...props}>
    {children}
  </Form.Text>
);

// ---------------------------------------------------------------------------
// Checkbox  →  Form.Check  (label is the children)
// ---------------------------------------------------------------------------
export const Checkbox = ({
  children,
  checked,
  defaultChecked,
  onChange,
  disabled,
  inline,
  className, // swallowed – BS5 doesn't use this prop
  ...rest
}) => (
  <Form.Check
    type="checkbox"
    label={children}
    checked={checked}
    defaultChecked={defaultChecked}
    onChange={onChange}
    disabled={disabled}
    inline={inline}
    className={className}
    {...rest}
  />
);

// ---------------------------------------------------------------------------
// Grid  →  Container
// ---------------------------------------------------------------------------
export const Grid = ({ children, fluid, className, bsClass, ...props }) => (
  <Container fluid={fluid} className={[className, bsClass].filter(Boolean).join(' ')} {...props}>
    {children}
  </Container>
);

// ---------------------------------------------------------------------------
// PageHeader  →  plain <div class="page-header">
// ---------------------------------------------------------------------------
export const PageHeader = ({ children, ...props }) => (
  <div className="page-header" {...props}>
    <h1>{children}</h1>
  </div>
);

// ---------------------------------------------------------------------------
// PanelGroup  →  plain <div class="panel-group">
// Accordion wrapping broke because the panels inside are not AccordionItems.
// BS3 PanelGroup is just a grouping div — replicate that directly.
// ---------------------------------------------------------------------------
export const PanelGroup = ({ children, className = '', accordion, ...props }) => (
  <div className={`panel-group ${className}`.trim()} {...props}>{children}</div>
);

// ---------------------------------------------------------------------------
// FormGroup  →  <div class="form-group"> shim
// RB2 Form.Group no longer emits form-group class; validationState maps to
// has-error / has-warning / has-success for BS3 CSS selectors.
// ---------------------------------------------------------------------------
export const FormGroup = ({ children, className = '', validationState, ...props }) => {
  const stateClass = validationState === 'error' ? 'has-error'
                   : validationState === 'warning' ? 'has-warning'
                   : validationState === 'success' ? 'has-success'
                   : '';
  return (
    <div className={`form-group ${stateClass} ${className}`.trim()} {...props}>
      {children}
    </div>
  );
};

// ---------------------------------------------------------------------------
// Label  →  <span class="label label-{variant}">  (BS3 etalon DOM)
// RB v2 dropped <Label>; using <Badge> renders <span class="badge bg-danger">,
// which yeti.css styles incorrectly: `.badge { bg: #008cba }` (line 4018) comes
// AFTER `.bg-danger { bg: #f2dede }` (line 421) and wins because of equal
// specificity, so every variant ends up rendering blue.
// Render the etalon DOM directly so yeti's `.label.label-danger`,
// `.label-info`, `.label-success`, ... fire as designed.
// Accepts both the old bsStyle prop AND the newer variant prop.
// ---------------------------------------------------------------------------
export const Label = ({ children, bsStyle, variant, className = '', ...props }) => {
  const effective = bsStyle || variant || 'default';
  const labelClass = classNames('label', `label-${effective}`, className);
  return (
    <span className={labelClass} {...props}>
      {children}
    </span>
  );
};

// ---------------------------------------------------------------------------
// InputGroupButton  →  <div class="input-group-btn"> shim
// RB2 removed InputGroup.Button. BS3 theme (yeti.css) requires the
// .input-group-btn wrapper for correct table-cell layout and border-radius.
// Usage:  import { InputGroupButton } from '@/common/BootstrapCompat';
//         <InputGroupButton><Button>Go</Button></InputGroupButton>
// For DropdownButton with as={InputGroup.Button}: wrap in InputGroupButton and
// remove the as prop instead.
// ---------------------------------------------------------------------------
export const InputGroupButton = ({ children, className = '', ...props }) => (
  <div className={`input-group-btn ${className}`.trim()} {...props}>
    {children}
  </div>
);

// ---------------------------------------------------------------------------
// Well  →  <div class="well"> shim
// Bootstrap 5 / RB2 dropped the Well component. BS3 theme has .well styles.
// ---------------------------------------------------------------------------
export const Well = ({ children, bsSize, size, className = '', ...props }) => {
  const sizeClass = (bsSize || size) === 'sm' ? 'well-sm'
                  : (bsSize || size) === 'lg' ? 'well-lg'
                  : '';
  return (
    <div className={`well ${sizeClass} ${className}`.trim()} {...props}>
      {children}
    </div>
  );
};

export const NavDropdownCompat = ({ id, title, children, align, active }) => {
  const [show, setShow] = useState(false);
  const ref = useRef(null);

  useEffect(() => {
    if (!show) return;
    const onOutside = (e) => {
      if (ref.current && !ref.current.contains(e.target)) setShow(false);
    };
    document.addEventListener('mousedown', onOutside);
    return () => document.removeEventListener('mousedown', onOutside);
  }, [show]);

  const menuClass = classNames('dropdown-menu', {
    'dropdown-menu-right': align === 'end',
  });

  return (
    <li ref={ref} className={classNames('dropdown', { open: show, active })}>
      <a
        id={id}
        href="#"
        role="button"
        className="dropdown-toggle"
        aria-haspopup="true"
        aria-expanded={show}
        onClick={(e) => { e.preventDefault(); setShow(s => !s); }}
      >
        {title}
      </a>
      {show && (
        <ul role="menu" className={menuClass} aria-labelledby={id} onClick={() => setShow(false)}>
          {children}
        </ul>
      )}
    </li>
  );
};

export const NavDropdownItem = ({ href, onClick, active, children }) => (
  <li role="presentation" className={active ? 'active' : ''}>
    <a role="menuitem" tabIndex={-1} href={href || '#'} onClick={onClick}>
      {children}
    </a>
  </li>
);
