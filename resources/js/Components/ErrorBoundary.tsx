import React, { Component, ErrorInfo, ReactNode } from 'react';
import { AlertCircle } from 'lucide-react';

interface Props {
    children: ReactNode;
    fallback?: ReactNode;
}

interface State {
    hasError: boolean;
    error: Error | null;
}

export default class ErrorBoundary extends Component<Props, State> {
    public state: State = {
        hasError: false,
        error: null,
    };

    public static getDerivedStateFromError(error: Error): State {
        return { hasError: true, error };
    }

    public componentDidCatch(error: Error, errorInfo: ErrorInfo) {
        console.error('Uncaught error:', error, errorInfo);
    }

    public render() {
        if (this.state.hasError) {
            if (this.props.fallback) return this.props.fallback;
            
            return (
                <div className="flex flex-col items-center justify-center h-full p-8 text-red-500 bg-white">
                    <AlertCircle className="w-10 h-10 mb-4" />
                    <p className="text-sm font-medium text-center mb-2">Ops! Ocorreu um erro ao renderizar este componente.</p>
                    <p className="text-xs text-neutral-500">{this.state.error?.message}</p>
                </div>
            );
        }

        return this.props.children;
    }
}
