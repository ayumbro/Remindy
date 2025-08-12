import React, { useState, useEffect } from 'react';
import { Check, ChevronDown, Plus } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { router } from '@inertiajs/react';

interface PaymentMethod {
    id: number;
    name: string;
    description?: string;
    is_active: boolean;
}

interface PaymentMethodSelectorProps {
    paymentMethods: PaymentMethod[];
    selectedPaymentMethodId: string;
    onPaymentMethodChange: (paymentMethodId: string) => void;
    onPaymentMethodCreated?: (paymentMethod: PaymentMethod) => void;
    placeholder?: string;
    disabled?: boolean;
    className?: string;
    error?: string;
    allowCreate?: boolean;
}

export default function PaymentMethodSelector({
    paymentMethods,
    selectedPaymentMethodId,
    onPaymentMethodChange,
    onPaymentMethodCreated,
    placeholder = "Select payment method...",
    disabled = false,
    className,
    error,
    allowCreate = true,
}: PaymentMethodSelectorProps) {
    const [open, setOpen] = useState(false);
    const [searchValue, setSearchValue] = useState('');
    const [isCreating, setIsCreating] = useState(false);
    const [availablePaymentMethods, setAvailablePaymentMethods] = useState(paymentMethods);

    // Update available payment methods when prop changes, but preserve locally created payment methods
    useEffect(() => {
        setAvailablePaymentMethods(prev => {
            // Get IDs of payment methods from props
            const propPaymentMethodIds = paymentMethods.map(method => method.id);

            // Keep locally created payment methods that aren't in the props yet
            const locallyCreatedPaymentMethods = prev.filter(method => !propPaymentMethodIds.includes(method.id));

            // Merge prop payment methods with locally created ones
            return [...paymentMethods, ...locallyCreatedPaymentMethods];
        });
    }, [paymentMethods]);

    // Find the selected payment method
    const selectedPaymentMethod = availablePaymentMethods.find(
        (method) => method.id.toString() === selectedPaymentMethodId
    );

    // Filter payment methods based on search
    const filteredPaymentMethods = availablePaymentMethods.filter((method) =>
        method.name.toLowerCase().includes(searchValue.toLowerCase())
    );

    // Check if we should show the create option
    const shouldShowCreateOption = allowCreate && 
        searchValue.trim().length > 0 && 
        !filteredPaymentMethods.some(method => 
            method.name.toLowerCase() === searchValue.toLowerCase()
        );

    // Function to create a new payment method
    const createPaymentMethod = (name: string): void => {
        if (isCreating) return;

        setIsCreating(true);

        router.post(route('api.payment-methods.store'), {
            name: name.trim(),
        }, {
            onSuccess: (page) => {
                // Extract the new payment method from the response
                const data = page.props as any;
                const newPaymentMethod: PaymentMethod = data.payment_method;

                // Add the new payment method to available options
                setAvailablePaymentMethods(prev => [...prev, newPaymentMethod]);

                // Notify parent component if callback provided
                onPaymentMethodCreated?.(newPaymentMethod);

                // Select the newly created payment method
                onPaymentMethodChange(newPaymentMethod.id.toString());

                // Close the popover and clear search
                setOpen(false);
                setSearchValue('');
                setIsCreating(false);
            },
            onError: (errors) => {
                console.error('Error creating payment method:', errors);

                // Handle validation errors
                if (errors.name) {
                    console.error('Payment method validation error:', Array.isArray(errors.name) ? errors.name[0] : errors.name);
                    // You might want to show a toast notification here
                } else {
                    console.error('Failed to create payment method');
                }
                setIsCreating(false);
            },
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleSelect = (paymentMethodId: string) => {
        onPaymentMethodChange(paymentMethodId);
        setOpen(false);
        setSearchValue('');
    };

    const handleCreateSelect = () => {
        if (searchValue.trim()) {
            createPaymentMethod(searchValue.trim());
        }
    };

    return (
        <div className="space-y-2">
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <Button
                        variant="outline"
                        role="combobox"
                        aria-expanded={open}
                        className={cn(
                            "w-full justify-between",
                            error && "border-destructive",
                            className
                        )}
                        disabled={disabled || isCreating}
                    >
                        {selectedPaymentMethod ? (
                            <span className="truncate">{selectedPaymentMethod.name}</span>
                        ) : selectedPaymentMethodId === "none" ? (
                            <span className="text-muted-foreground">No payment method</span>
                        ) : (
                            <span className="text-muted-foreground">{placeholder}</span>
                        )}
                        <ChevronDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="w-full p-0" align="start">
                    <Command>
                        <CommandInput
                            placeholder="Search payment methods..."
                            value={searchValue}
                            onValueChange={setSearchValue}
                            disabled={isCreating}
                        />
                        <CommandList>
                            <CommandEmpty>
                                {allowCreate ? (
                                    <div className="text-center py-6">
                                        <p className="text-sm text-muted-foreground mb-2">
                                            No payment methods found.
                                        </p>
                                        {searchValue.trim() && (
                                            <p className="text-xs text-muted-foreground">
                                                Type to create a new one.
                                            </p>
                                        )}
                                    </div>
                                ) : (
                                    <div className="text-center py-6">
                                        <p className="text-sm text-muted-foreground">
                                            No payment methods found.
                                        </p>
                                    </div>
                                )}
                            </CommandEmpty>
                            <CommandGroup>
                                {/* "No payment method" option */}
                                <CommandItem
                                    value="none"
                                    onSelect={() => handleSelect("none")}
                                    className="cursor-pointer"
                                >
                                    <Check
                                        className={cn(
                                            "mr-2 h-4 w-4",
                                            selectedPaymentMethodId === "none" ? "opacity-100" : "opacity-0"
                                        )}
                                    />
                                    <span className="text-muted-foreground">No payment method</span>
                                </CommandItem>

                                {/* Existing payment methods */}
                                {filteredPaymentMethods.map((method) => (
                                    <CommandItem
                                        key={method.id}
                                        value={method.name}
                                        onSelect={() => handleSelect(method.id.toString())}
                                        className="cursor-pointer"
                                    >
                                        <Check
                                            className={cn(
                                                "mr-2 h-4 w-4",
                                                selectedPaymentMethodId === method.id.toString() ? "opacity-100" : "opacity-0"
                                            )}
                                        />
                                        <div className="flex flex-col">
                                            <span>{method.name}</span>
                                            {method.description && (
                                                <span className="text-xs text-muted-foreground">
                                                    {method.description}
                                                </span>
                                            )}
                                        </div>
                                    </CommandItem>
                                ))}

                                {/* Create new payment method option */}
                                {shouldShowCreateOption && (
                                    <CommandItem
                                        value={`create-${searchValue}`}
                                        onSelect={handleCreateSelect}
                                        className="cursor-pointer text-primary"
                                        disabled={isCreating}
                                    >
                                        <Plus className="mr-2 h-4 w-4" />
                                        <span>
                                            {isCreating ? 'Creating...' : `Create "${searchValue.trim()}"`}
                                        </span>
                                    </CommandItem>
                                )}
                            </CommandGroup>
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>
            {error && (
                <p className="text-sm text-destructive">{error}</p>
            )}
        </div>
    );
}
