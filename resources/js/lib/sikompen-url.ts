type FormDefinition = {
    action: string;
};

function publicPath(): string {
    if (typeof document === 'undefined') {
        return '';
    }

    const value = document
        .querySelector('meta[name="sikompen-public-path"]')
        ?.getAttribute('content');

    if (!value || value === '/') {
        return '';
    }

    return `/${value.replace(/^\/+|\/+$/g, '')}`;
}

export function sikompenUrl(url: string): string {
    if (!url.startsWith('/') || url.startsWith('//')) {
        return url;
    }

    const basePath = publicPath();

    if (!basePath || url === basePath || url.startsWith(`${basePath}/`)) {
        return url;
    }

    return url === '/' ? `${basePath}/` : `${basePath}${url}`;
}

export function sikompenForm<T extends FormDefinition>(form: T): T {
    return {
        ...form,
        action: sikompenUrl(form.action),
    };
}
