import React from 'react';

interface ErrorProps {
    success: boolean;
    message: string;
}

/**
 * This component is used for API error responses from inline creation endpoints.
 * It doesn't render anything visible - it just provides the data through props
 * that the frontend components can access via the Inertia response.
 */
export default function ApiError(props: ErrorProps) {
    // This component doesn't render anything - it's just a data container
    // The frontend components access the data through page.props
    return null;
}
