// Credit: https://usehooks-ts.com/
import { useState } from 'react';

export type CopiedValue = string | null;
export type CopyFn = (text: string) => Promise<boolean>;
export type UseClipboardReturn = [CopiedValue, CopyFn];

function copyWithDocument(text: string): boolean {
    if (typeof document === 'undefined') {
        return false;
    }

    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.setAttribute('readonly', '');
    textArea.setAttribute('aria-hidden', 'true');
    textArea.style.position = 'fixed';
    textArea.style.opacity = '0';

    try {
        document.body.appendChild(textArea);
        textArea.select();

        return document.execCommand('copy');
    } finally {
        textArea.remove();
    }
}

export function useClipboard(): UseClipboardReturn {
    const [copiedText, setCopiedText] = useState<CopiedValue>(null);

    const copy: CopyFn = async (text) => {
        try {
            if (typeof navigator !== 'undefined' && navigator.clipboard !== undefined) {
                await navigator.clipboard.writeText(text);
                setCopiedText(text);

                return true;
            }
        } catch (error) {
            console.warn('Clipboard API copy failed', error);
        }

        try {
            const didCopy = copyWithDocument(text);

            setCopiedText(didCopy ? text : null);

            return didCopy;
        } catch (error) {
            console.warn('Document copy failed', error);
            setCopiedText(null);

            return false;
        }
    };

    return [copiedText, copy];
}
