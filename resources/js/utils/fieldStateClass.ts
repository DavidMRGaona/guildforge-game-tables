/**
 * Border and focus colours of a text field of the web forms. A field with an error keeps the error
 * colour in both themes, also while focused: the two sets never meet on one element, so neither
 * has to win over the other.
 */
export function fieldStateClass(invalid: boolean): string {
    return invalid
        ? 'border-error hover:border-error focus:border-error focus:ring-error'
        : 'border-default hover:border-strong focus:border-primary-500 focus:ring-primary-500';
}
