/**
 * FieldBox — fieldset/legend-style form field wrapper.
 *
 * Usage:
 *   <FieldBox label="Site URL" hint="Must be unique.">
 *     <input type="url" className="field-box-control" ... />
 *   </FieldBox>
 *
 *   <FieldBox label="Agent">
 *     <select className="field-box-control" ...>...</select>
 *   </FieldBox>
 */
export default function FieldBox({ label, hint, children, className = '' }) {
  return (
    <fieldset className={`field-box ${className}`}>
      <legend>{label}</legend>
      {children}
      {hint && <p className="field-box-hint">{hint}</p>}
    </fieldset>
  );
}
