/**
 * Bootstrap 3 → Bootstrap 5 / react-bootstrap v0.33 → v2 compatibility shim.
 * Provides wrappers for removed/renamed components so existing JSX doesn't need to change.
 */
import React, { useState } from 'react';
import { Card, Form, Container, Badge, Collapse } from 'react-bootstrap';
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
  const [internalExpanded, setInternalExpanded] = useState(defaultExpanded !== false);
  const isControlled = typeof expanded === 'boolean';
  const isExpanded = isControlled ? expanded : internalExpanded;
  const normalizeHeaderNode = (headerValue) => {
    if (typeof headerValue === 'string') {
      return <h3 className="panel-title">{headerValue}</h3>;
    }
    if (React.isValidElement(headerValue) && typeof headerValue.type === 'string' && /^h[1-6]$/.test(headerValue.type)) {
      return <h3 className="panel-title">{headerValue.props.children}</h3>;
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
        <button type="button" className={isExpanded ? '' : 'collapsed'} onClick={onToggle}>{headerContent}</button>
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

  return (
    <Card className={panelClassName} style={style} {...rest}>
      {header && <div className="panel-heading">{headerNode}</div>}
      <div className="panel-body">{children}</div>
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
// Label  →  Badge  (react-bootstrap Label with bsStyle/variant → Badge with bg)
// Accepts both the old bsStyle prop AND the newer variant prop (some call sites
// already use variant="info" etc.) so that either form works.
// ---------------------------------------------------------------------------
export const Label = ({ children, bsStyle, variant, className = '', ...props }) => {
  // Resolve effective style: prefer bsStyle (BS3 API), fall back to variant
  const effective = bsStyle || variant || 'default';
  const bg = effective === 'default' ? 'secondary' : effective;
  return (
    <Badge bg={bg} className={className} {...props}>
      {children}
    </Badge>
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
