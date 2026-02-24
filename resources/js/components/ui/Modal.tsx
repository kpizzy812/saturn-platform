import * as React from 'react';
import { Dialog, DialogPanel, DialogTitle, Transition, TransitionChild } from '@headlessui/react';
import { cn } from '@/lib/utils';

interface ModalProps {
    isOpen: boolean;
    onClose: () => void;
    title?: string;
    description?: string;
    children: React.ReactNode;
    size?: 'sm' | 'default' | 'lg' | 'xl' | 'full';
}

const sizeClasses = {
    sm: 'max-w-sm',
    default: 'max-w-md',
    lg: 'max-w-lg',
    xl: 'max-w-xl',
    full: 'max-w-4xl',
};

export function Modal({ isOpen, onClose, title, description, children, size = 'default' }: ModalProps) {
    return (
        <Transition show={isOpen}>
            <Dialog onClose={onClose} className="relative z-50">
                {/* Backdrop */}
                <TransitionChild
                    enter="ease-out duration-300"
                    enterFrom="opacity-0"
                    enterTo="opacity-100"
                    leave="ease-in duration-200"
                    leaveFrom="opacity-100"
                    leaveTo="opacity-0"
                >
                    <div className="fixed inset-0 bg-black/60 backdrop-blur-sm" />
                </TransitionChild>

                {/* Modal */}
                <div className="fixed inset-0 flex items-center justify-center p-4">
                    <TransitionChild
                        enter="ease-out duration-300"
                        enterFrom="opacity-0 scale-95"
                        enterTo="opacity-100 scale-100"
                        leave="ease-in duration-200"
                        leaveFrom="opacity-100 scale-100"
                        leaveTo="opacity-0 scale-95"
                    >
                        <DialogPanel
                            className={cn(
                                'w-full max-h-[85vh] overflow-y-auto rounded-xl border border-white/[0.08] bg-white/[0.05] backdrop-blur-2xl backdrop-saturate-150 p-6 shadow-2xl',
                                sizeClasses[size]
                            )}
                        >
                            {title && (
                                <DialogTitle className="text-lg font-semibold text-foreground">
                                    {title}
                                </DialogTitle>
                            )}
                            {description && (
                                <p className="mt-1 text-sm text-foreground-muted">
                                    {description}
                                </p>
                            )}
                            <div className={cn(title || description ? 'mt-4' : '')}>
                                {children}
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </Dialog>
        </Transition>
    );
}

// Modal footer for action buttons
export function ModalFooter({ children, className }: { children: React.ReactNode; className?: string }) {
    return (
        <div className={cn('mt-6 flex items-center justify-end gap-3', className)}>
            {children}
        </div>
    );
}
