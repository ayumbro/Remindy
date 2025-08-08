import { type BreadcrumbItem } from '@/types';
import { Transition } from '@headlessui/react';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { AlertCircle, Edit2, Lock, Plus, Shield, Trash2, Unlock } from 'lucide-react';
import { FormEventHandler, useState } from 'react';

import HeadingSmall from '@/components/heading-small';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Currency settings',
        href: '/settings/currencies',
    },
];

interface Currency {
    id: number;
    code: string;
    name: string;
    symbol: string;
    is_active: boolean;
    subscriptions_count?: number;
    payment_histories_count?: number;
}

interface UserCurrencySettings {
    default_currency_id: number | null;
    enabled_currencies: number[];
}

interface CurrenciesProps {
    allCurrencies: Currency[];
    userCurrencySettings: UserCurrencySettings;
}

export default function Currencies({ allCurrencies = [], userCurrencySettings }: CurrenciesProps) {
    const { errors: pageErrors } = usePage().props as any;
    const [showAddForm, setShowAddForm] = useState(false);
    const [currencyToDelete, setCurrencyToDelete] = useState<Currency | null>(null);
    const [currencyToEdit, setCurrencyToEdit] = useState<Currency | null>(null);
    const [deletionError, setDeletionError] = useState<string | null>(null);
    const { data, setData, patch, errors, processing, recentlySuccessful } = useForm({
        default_currency_id: userCurrencySettings.default_currency_id?.toString() || 'none',
        enabled_currencies: userCurrencySettings.enabled_currencies || [],
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        patch(route('settings.currencies.update'), {
            data: {
                default_currency_id: data.default_currency_id !== 'none' ? parseInt(data.default_currency_id) : null,
                enabled_currencies: data.enabled_currencies,
            },
        });
    };

    const handleCurrencyToggle = (currencyId: number, enabled: boolean) => {
        if (enabled) {
            setData('enabled_currencies', [...data.enabled_currencies, currencyId]);
        } else {
            const newEnabledCurrencies = data.enabled_currencies.filter((id) => id !== currencyId);
            setData('enabled_currencies', newEnabledCurrencies);

            // If the disabled currency was the default, clear the default
            if (data.default_currency_id === currencyId.toString()) {
                setData('default_currency_id', 'none');
            }
        }
    };

    // Add currency form
    const {
        data: addData,
        setData: setAddData,
        post,
        processing: addProcessing,
        errors: addErrors,
        reset: resetAdd,
    } = useForm({
        code: '',
        name: '',
        symbol: '',
    });

    // Edit currency form
    const {
        data: editData,
        setData: setEditData,
        patch: patchEdit,
        processing: editProcessing,
        errors: editErrors,
        reset: resetEdit,
    } = useForm({
        name: '',
        symbol: '',
    });

    const enabledCurrencies = allCurrencies.filter((currency) => data.enabled_currencies.includes(currency.id));

    const handleAddCurrency: FormEventHandler = (e) => {
        e.preventDefault();

        // Client-side validation
        if (!addData.code.trim() || addData.code.length !== 3) {
            return;
        }
        if (!addData.name.trim()) {
            return;
        }
        if (!addData.symbol.trim()) {
            return;
        }

        // Check if currency code already exists
        const existingCurrency = allCurrencies.find((c) => c.code.toUpperCase() === addData.code.toUpperCase());
        if (existingCurrency) {
            return;
        }

        post(route('settings.currencies.store'), {
            onSuccess: () => {
                resetAdd();
                setShowAddForm(false);
            },
            onError: (errors) => {
                console.error('Currency creation failed:', errors);
            },
        });
    };

    const handleDeleteCurrency = (currency: Currency) => {
        // Clear any previous error
        setDeletionError(null);

        router.delete(route('settings.currencies.destroy', currency.id), {
            onSuccess: () => {
                setCurrencyToDelete(null);
                setDeletionError(null);
            },
            onError: (errors) => {
                // Handle validation errors from the backend
                if (errors.currency) {
                    setDeletionError(errors.currency);
                } else {
                    setDeletionError('An error occurred while trying to delete the currency.');
                }
            },
        });
    };

    const handleEditCurrency = (currency: Currency) => {
        setCurrencyToEdit(currency);
        setEditData({
            name: currency.name,
            symbol: currency.symbol,
        });
    };

    const handleUpdateCurrency: FormEventHandler = (e) => {
        e.preventDefault();

        if (!currencyToEdit) return;

        patchEdit(route('settings.currencies.update-currency', currencyToEdit.id), {
            onSuccess: () => {
                resetEdit();
                setCurrencyToEdit(null);
            },
            onError: (errors) => {
                console.error('Currency update failed:', errors);
            },
        });
    };

    const closeDialog = () => {
        setCurrencyToDelete(null);
        setCurrencyToEdit(null);
        setDeletionError(null);
        resetEdit();
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Currency settings" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Currency preferences"
                        description="Manage your preferred currencies and set a default currency for new subscriptions"
                    />

                    <form onSubmit={submit} className="space-y-6">
                        {/* Currencies */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center justify-between">
                                    <span className="flex items-center gap-2">
                                        <Shield className="h-5 w-5 text-blue-600" />
                                        Currencies
                                    </span>
                                    <Dialog open={showAddForm} onOpenChange={setShowAddForm}>
                                        <DialogTrigger asChild>
                                            <Button size="sm">
                                                <Plus className="mr-2 h-4 w-4" />
                                                Add Currency
                                            </Button>
                                        </DialogTrigger>
                                        <DialogContent>
                                            <DialogHeader>
                                                <DialogTitle>Add New Currency</DialogTitle>
                                                <DialogDescription>Create a custom currency for your subscriptions</DialogDescription>
                                            </DialogHeader>
                                            <form onSubmit={handleAddCurrency} className="space-y-4">
                                                <div className="grid gap-2">
                                                    <Label htmlFor="add-currency-code">Currency Code *</Label>
                                                    <Input
                                                        id="add-currency-code"
                                                        name="code"
                                                        type="text"
                                                        maxLength={3}
                                                        value={addData.code}
                                                        onChange={(e) => setAddData('code', e.target.value.toUpperCase())}
                                                        placeholder="EUR"
                                                        disabled={addProcessing}
                                                        autoComplete="off"
                                                        aria-describedby="add-currency-code-help"
                                                        required
                                                    />
                                                    <InputError message={addErrors.code} />
                                                    <p id="add-currency-code-help" className="text-muted-foreground text-xs">
                                                        3-letter ISO currency code (e.g., EUR, GBP)
                                                    </p>
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="add-currency-name">Currency Name *</Label>
                                                    <Input
                                                        id="add-currency-name"
                                                        name="name"
                                                        type="text"
                                                        value={addData.name}
                                                        onChange={(e) => setAddData('name', e.target.value)}
                                                        placeholder="Euro"
                                                        disabled={addProcessing}
                                                        autoComplete="off"
                                                        required
                                                    />
                                                    <InputError message={addErrors.name} />
                                                </div>
                                                <div className="grid gap-2">
                                                    <Label htmlFor="add-currency-symbol">Currency Symbol *</Label>
                                                    <Input
                                                        id="add-currency-symbol"
                                                        name="symbol"
                                                        type="text"
                                                        value={addData.symbol}
                                                        onChange={(e) => setAddData('symbol', e.target.value)}
                                                        placeholder="€"
                                                        disabled={addProcessing}
                                                        autoComplete="off"
                                                        required
                                                    />
                                                    <InputError message={addErrors.symbol} />
                                                </div>
                                                <DialogFooter>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        onClick={() => {
                                                            resetAdd();
                                                            setShowAddForm(false);
                                                        }}
                                                    >
                                                        Cancel
                                                    </Button>
                                                    <Button type="submit" disabled={addProcessing}>
                                                        {addProcessing ? 'Creating...' : 'Create Currency'}
                                                    </Button>
                                                </DialogFooter>
                                            </form>
                                        </DialogContent>
                                    </Dialog>
                                </CardTitle>
                                <CardDescription>Select which currencies you want to use for your subscriptions</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="grid gap-4 md:grid-cols-2">
                                    {allCurrencies.map((currency) => {
                                        const isInUse = (currency.subscriptions_count || 0) > 0 || (currency.payment_histories_count || 0) > 0;
                                        const canDelete = !isInUse;

                                        return (
                                            <div
                                                key={currency.id}
                                                className={`flex items-start space-x-3 rounded-lg border p-3 ${isInUse ? 'border-blue-200 bg-blue-50/50' : 'border-gray-200'}`}
                                            >
                                                <Checkbox
                                                    id={`currency-${currency.id}`}
                                                    name={`currency-${currency.id}`}
                                                    checked={data.enabled_currencies.includes(currency.id)}
                                                    onCheckedChange={(checked) => handleCurrencyToggle(currency.id, checked as boolean)}
                                                    aria-describedby={`currency-${currency.id}-description`}
                                                    className="mt-0.5 flex-shrink-0"
                                                />
                                                <div className="min-w-0 flex-1">
                                                    <div className="mb-1 flex items-center gap-2">
                                                        <Label
                                                            htmlFor={`currency-${currency.id}`}
                                                            className="cursor-pointer truncate text-sm font-medium"
                                                        >
                                                            {currency.symbol} {currency.code}
                                                        </Label>
                                                        {isInUse ? (
                                                            <Lock
                                                                className="h-3 w-3 flex-shrink-0 text-blue-500"
                                                                title="Currency is in use and cannot be deleted"
                                                            />
                                                        ) : (
                                                            <Unlock
                                                                className="h-3 w-3 flex-shrink-0 text-green-500"
                                                                title="Currency can be deleted"
                                                            />
                                                        )}
                                                    </div>
                                                    <p
                                                        id={`currency-${currency.id}-description`}
                                                        className="text-muted-foreground mb-1 truncate text-xs"
                                                    >
                                                        {currency.name}
                                                    </p>
                                                    {isInUse && (
                                                        <div className="mt-1 flex flex-wrap gap-1">
                                                            {currency.subscriptions_count > 0 && (
                                                                <Badge variant="secondary" className="h-auto px-1.5 py-0.5 text-xs">
                                                                    {currency.subscriptions_count} sub{currency.subscriptions_count !== 1 ? 's' : ''}
                                                                </Badge>
                                                            )}
                                                            {currency.payment_histories_count > 0 && (
                                                                <Badge variant="outline" className="h-auto px-1.5 py-0.5 text-xs">
                                                                    {currency.payment_histories_count} payment
                                                                    {currency.payment_histories_count !== 1 ? 's' : ''}
                                                                </Badge>
                                                            )}
                                                        </div>
                                                    )}
                                                </div>
                                                <div className="ml-2 flex flex-shrink-0 items-start gap-2">
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        className="text-blue-600 hover:text-blue-700"
                                                        onClick={() => handleEditCurrency(currency)}
                                                        title="Edit currency details"
                                                    >
                                                        <Edit2 className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        className={canDelete ? 'text-red-600 hover:text-red-700' : 'cursor-not-allowed text-gray-400'}
                                                        onClick={() => setCurrencyToDelete(currency)}
                                                        disabled={!canDelete}
                                                        title={canDelete ? 'Delete currency' : 'Cannot delete currency that is in use'}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                                <InputError className="mt-2" message={errors.enabled_currencies} />
                                {data.enabled_currencies.length === 0 && (
                                    <p className="mt-2 text-sm text-red-600">At least one currency must be enabled.</p>
                                )}
                            </CardContent>
                        </Card>

                        {/* Default Currency */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Default Currency</CardTitle>
                                <CardDescription>Choose your preferred currency for new subscriptions</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <div className="grid gap-2">
                                    <Label htmlFor="default-currency-select">Default Currency</Label>
                                    <Select
                                        value={data.default_currency_id}
                                        onValueChange={(value) => setData('default_currency_id', value)}
                                        name="default_currency_id"
                                    >
                                        <SelectTrigger id="default-currency-select" aria-describedby="default-currency-help">
                                            <SelectValue placeholder="Select default currency (optional)" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">No default currency</SelectItem>
                                            {enabledCurrencies.map((currency) => (
                                                <SelectItem key={currency.id} value={currency.id.toString()}>
                                                    {currency.symbol} {currency.code} - {currency.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError className="mt-2" message={errors.default_currency_id} />
                                    <p id="default-currency-help" className="text-muted-foreground text-xs">
                                        This currency will be pre-selected when creating new subscriptions
                                    </p>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Currency Usage Summary */}
                        {data.enabled_currencies.length > 0 && (
                            <Card>
                                <CardHeader>
                                    <CardTitle>Summary</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <div className="space-y-2">
                                        <p className="text-sm">
                                            <span className="font-medium">Enabled currencies:</span> {enabledCurrencies.map((c) => c.code).join(', ')}
                                        </p>
                                        {data.default_currency_id && data.default_currency_id !== 'none' && (
                                            <p className="text-sm">
                                                <span className="font-medium">Default currency:</span>{' '}
                                                {enabledCurrencies.find((c) => c.id.toString() === data.default_currency_id)?.code}
                                            </p>
                                        )}
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        <div className="flex items-center gap-4">
                            <Button disabled={processing || data.enabled_currencies.length === 0}>Save Currency Preferences</Button>

                            <Transition
                                show={recentlySuccessful}
                                enter="transition ease-in-out"
                                enterFrom="opacity-0"
                                leave="transition ease-in-out"
                                leaveTo="opacity-0"
                            >
                                <p className="text-sm text-green-600">Saved successfully!</p>
                            </Transition>
                        </div>
                    </form>
                </div>

                {/* Delete Confirmation Dialog */}
                <Dialog open={!!currencyToDelete} onOpenChange={closeDialog}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Delete Currency</DialogTitle>
                            <DialogDescription>
                                Are you sure you want to delete the currency "{currencyToDelete?.code} - {currencyToDelete?.name}"? This action cannot
                                be undone.
                            </DialogDescription>
                        </DialogHeader>

                        {/* Usage Information */}
                        {currencyToDelete && (currencyToDelete.subscriptions_count > 0 || currencyToDelete.payment_histories_count > 0) && (
                            <Alert variant="destructive">
                                <AlertCircle className="h-4 w-4" />
                                <AlertDescription>
                                    <div className="space-y-2">
                                        <p className="font-medium">This currency cannot be deleted because it is currently in use:</p>
                                        <ul className="list-inside list-disc space-y-1 text-sm">
                                            {currencyToDelete.subscriptions_count > 0 && (
                                                <li>
                                                    {currencyToDelete.subscriptions_count} subscription
                                                    {currencyToDelete.subscriptions_count !== 1 ? 's' : ''}
                                                </li>
                                            )}
                                            {currencyToDelete.payment_histories_count > 0 && (
                                                <li>
                                                    {currencyToDelete.payment_histories_count} payment
                                                    {currencyToDelete.payment_histories_count !== 1 ? 's' : ''}
                                                </li>
                                            )}
                                        </ul>
                                        <p className="mt-2 text-sm">
                                            To delete this currency, please first remove or reassign all subscriptions and ensure no payment history
                                            exists.
                                        </p>
                                    </div>
                                </AlertDescription>
                            </Alert>
                        )}

                        <DialogFooter>
                            <Button variant="outline" onClick={closeDialog}>
                                Cancel
                            </Button>
                            <Button
                                variant="destructive"
                                onClick={() => currencyToDelete && handleDeleteCurrency(currencyToDelete)}
                                disabled={
                                    currencyToDelete && (currencyToDelete.subscriptions_count > 0 || currencyToDelete.payment_histories_count > 0)
                                }
                                title={
                                    currencyToDelete && (currencyToDelete.subscriptions_count > 0 || currencyToDelete.payment_histories_count > 0)
                                        ? 'Cannot delete currency that is currently in use'
                                        : ''
                                }
                            >
                                {currencyToDelete && (currencyToDelete.subscriptions_count > 0 || currencyToDelete.payment_histories_count > 0)
                                    ? 'Cannot Delete'
                                    : 'Delete Currency'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                {/* Edit Currency Dialog */}
                <Dialog open={!!currencyToEdit} onOpenChange={closeDialog}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Edit Currency</DialogTitle>
                            <DialogDescription>Update the details for "{currencyToEdit?.code}" currency.</DialogDescription>
                        </DialogHeader>
                        <form onSubmit={handleUpdateCurrency} className="space-y-4">
                            <div className="grid gap-2">
                                <Label htmlFor="edit-currency-name">Currency Name *</Label>
                                <Input
                                    id="edit-currency-name"
                                    type="text"
                                    value={editData.name}
                                    onChange={(e) => setEditData('name', e.target.value)}
                                    placeholder="e.g., US Dollar"
                                    required
                                />
                                <InputError className="mt-1" message={editErrors.name} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="edit-currency-symbol">Currency Symbol *</Label>
                                <Input
                                    id="edit-currency-symbol"
                                    type="text"
                                    value={editData.symbol}
                                    onChange={(e) => setEditData('symbol', e.target.value)}
                                    placeholder="e.g., $"
                                    required
                                />
                                <InputError className="mt-1" message={editErrors.symbol} />
                            </div>
                            <DialogFooter>
                                <Button type="button" variant="outline" onClick={closeDialog}>
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={editProcessing}>
                                    {editProcessing ? 'Updating...' : 'Update Currency'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </SettingsLayout>
        </AppLayout>
    );
}
