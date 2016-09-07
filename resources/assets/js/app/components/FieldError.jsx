import React, { PropTypes } from 'react';
import { Label } from 'react-bootstrap';

const FieldError = (props) => {
  const {
    touched,
    error,
  } = props;

  if (touched && error) {
    return (<Label bsStyle="danger">{error}</Label>);
  }

  return null;
};

FieldError.propTypes = {
  touched: PropTypes.bool,
  error: PropTypes.string,
};

FieldError.defaultProps = {
  touched: false,
  error: '',
};

export default FieldError;