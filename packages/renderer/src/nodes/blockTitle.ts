import { createContext } from 'react';

/**
 * The title of the section or card a node sits in, so a field inside does not repeat it
 * ("Характеристики" over a repeater labelled "Характеристики").
 */
export const BlockTitleContext = createContext<string | null>(null);
