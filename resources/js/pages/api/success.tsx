import React from 'react';

interface SuccessProps {
    success: boolean;
    category?: {
        id: number;
        name: string;
        display_color: string;
    };
    payment_method?: {
        id: number;
        name: string;
        description: string | null;
        is_active: boolean;
    };
    message: string;
}

/**
 * This component is used for API responses from inline creation endpoints.
 * It doesn't render anything visible - it just provides the data through props
 * that the frontend components can access via the Inertia response.
 */
export default function ApiSuccess(props: SuccessProps) {
    // This component doesn't render anything - it's just a data container
    // The frontend components access the data through page.props
    return null;
}
