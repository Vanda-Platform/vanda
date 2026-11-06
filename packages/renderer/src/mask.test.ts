import { describe, expect, it } from 'vitest';
import { applyMask, maskedChange } from './mask';

const phone = '+7 (999) 999-99-99';

describe('input masks', () => {
  it('formats typed digits and skips what does not fit', () => {
    expect(applyMask(phone, '9123456789')).toBe('+7 (912) 345-67-89');
    expect(applyMask(phone, '79123456789')).toBe('+7 (912) 345-67-89');
    expect(applyMask(phone, '912abc34')).toBe('+7 (912) 34');
    expect(applyMask(phone, '9123456789000')).toBe('+7 (912) 345-67-89');
    expect(applyMask('aa-999', 'ab123')).toBe('ab-123');
  });

  it('keeps working while the user edits the formatted value', () => {
    expect(maskedChange(phone, '+7 (912) 3', '+7 (912) 34')).toBe('+7 (912) 34');
    expect(maskedChange(phone, '+7 (912) 3', '+7 (912) ')).toBe('+7 (912) ');
    expect(maskedChange(phone, '+7 (', '+7 ')).toBe('+7 ');
    // A literal deleted in the middle would come straight back; the last character goes instead.
    expect(maskedChange(phone, '+7 (912) 345', '+7 (912 345')).toBe('+7 (912) 34');
  });
});
