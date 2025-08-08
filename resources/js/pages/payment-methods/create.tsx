import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle, Upload, X } from 'lucide-react';
import { FormEventHandler, useRef, useState } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Payment Methods',
        href: '/payment-methods',
    },
    {
        title: 'Create',
        href: '/payment-methods/create',
    },
];

interface CreatePaymentMethodProps {}

interface PaymentMethodForm {
    name: string;
    description: string;
    image: File | null;
    is_active: boolean;
}

export default function CreatePaymentMethod({}: CreatePaymentMethodProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [imagePreview, setImagePreview] = useState<string | null>(null);
    const [showDeleteImageDialog, setShowDeleteImageDialog] = useState(false);
    const [isRemovingImage, setIsRemovingImage] = useState(false);
    const [isImageMarkedForDeletion, setIsImageMarkedForDeletion] = useState(false);

    const { data, setData, post, processing, errors, reset } = useForm<PaymentMethodForm>({
        name: '',
        description: '',
        image: null,
        is_active: true,
    });

    const handleImageChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            setData('image', file);
            setIsImageMarkedForDeletion(false); // Reset deletion state when new image is uploaded

            // Create preview
            const reader = new FileReader();
            reader.onload = (e) => {
                setImagePreview(e.target?.result as string);
            };
            reader.readAsDataURL(file);
        }
    };

    const removeImage = () => {
        setShowDeleteImageDialog(true);
    };

    const confirmRemoveImage = () => {
        setIsRemovingImage(true);

        // Add a small delay to show the loading state
        setTimeout(() => {
            setData('image', null);
            setImagePreview(null);
            setIsImageMarkedForDeletion(true); // Mark image for deletion visually
            if (fileInputRef.current) {
                fileInputRef.current.value = '';
            }
            setShowDeleteImageDialog(false);
            setIsRemovingImage(false);
        }, 300);
    };

    const undoImageDeletion = () => {
        setIsImageMarkedForDeletion(false);
        // Note: In create page, we can't restore the original image since there wasn't one
        // This function is mainly for consistency with the edit page
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        // Client-side validation
        if (!data.name.trim()) {
            return;
        }

        // Check if we need to use FormData (for file uploads)
        const hasImage = data.image !== null;

        if (hasImage) {
            // Use FormData for file uploads
            const formData = new FormData();

            // Add form fields to FormData, ensuring no null/undefined values
            formData.append('name', data.name || '');
            formData.append('description', data.description || '');
            formData.append('is_active', data.is_active ? '1' : '0');

            // Add image file to FormData only if it's a valid File object
            if (data.image && data.image instanceof File) {
                formData.append('image', data.image);
            }

            post('/payment-methods', {
                data: formData,
                forceFormData: true,
                onSuccess: () => {
                    reset();
                    setImagePreview(null);
                    if (fileInputRef.current) {
                        fileInputRef.current.value = '';
                    }
                },
                onError: (errors) => {
                    console.error('Payment method creation failed:', errors);
                    alert('Failed to create payment method. Please check your input and try again.');
                },
            });
        } else {
            // Use regular form submission when no files
            post('/payment-methods', {
                onSuccess: () => {
                    reset();
                    setImagePreview(null);
                    if (fileInputRef.current) {
                        fileInputRef.current.value = '';
                    }
                },
            });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Payment Method" />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div>
                    <h1 className="text-3xl font-bold">Create Payment Method</h1>
                    <p className="text-muted-foreground">Add a new payment method for your subscriptions</p>
                </div>

                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle>Payment Method Details</CardTitle>
                        <CardDescription>Enter the details of your new payment method</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            {/* Basic Information */}
                            <div className="grid gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="payment-method-name">Payment Method Name *</Label>
                                    <Input
                                        id="payment-method-name"
                                        name="name"
                                        type="text"
                                        required
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        disabled={processing}
                                        placeholder="e.g., Chase Visa, PayPal Account"
                                        autoComplete="off"
                                    />
                                    <InputError message={errors.name} />
                                </div>
                            </div>

                            {/* Image Upload */}
                            <div className="space-y-2">
                                <Label htmlFor="payment-method-image">Payment Method Image</Label>
                                <div className="space-y-4">
                                    {imagePreview && !isImageMarkedForDeletion ? (
                                        <div className="relative">
                                            <img
                                                src={imagePreview}
                                                alt="Payment method preview"
                                                className="h-48 w-full max-w-md rounded-lg border object-cover"
                                            />
                                            <Button
                                                type="button"
                                                variant="destructive"
                                                size="sm"
                                                className="absolute top-2 right-2"
                                                onClick={removeImage}
                                            >
                                                <X className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    ) : isImageMarkedForDeletion ? (
                                        <div className="relative">
                                            <div className="flex h-48 w-full max-w-md flex-col items-center justify-center rounded-lg border border-dashed border-red-300 bg-gray-100">
                                                <div className="text-center">
                                                    <X className="mx-auto mb-2 h-12 w-12 text-red-400" />
                                                    <p className="mb-1 text-sm font-medium text-red-600">Image removed</p>
                                                    <p className="mb-3 text-xs text-red-500">Click below to upload a new image</p>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => fileInputRef.current?.click()}
                                                        className="border-blue-300 text-blue-600 hover:bg-blue-50"
                                                    >
                                                        Upload New Image
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    ) : (
                                        <div
                                            className="cursor-pointer rounded-lg border-2 border-dashed border-gray-300 p-6 text-center transition-colors hover:border-gray-400"
                                            onClick={() => fileInputRef.current?.click()}
                                        >
                                            <Upload className="mx-auto mb-4 h-12 w-12 text-gray-400" />
                                            <p className="mb-2 text-sm text-gray-600">Click to upload an image of your payment method</p>
                                            <p className="text-xs text-gray-500">PNG, JPG, GIF up to 2MB</p>
                                        </div>
                                    )}
                                    <Input
                                        ref={fileInputRef}
                                        id="payment-method-image"
                                        name="image"
                                        type="file"
                                        accept="image/*"
                                        onChange={handleImageChange}
                                        disabled={processing}
                                        className="hidden"
                                    />
                                    <InputError message={errors.image} />
                                </div>
                            </div>

                            {/* Description */}
                            <div className="space-y-2">
                                <Label htmlFor="payment-method-description">Description</Label>
                                <Textarea
                                    id="payment-method-description"
                                    name="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                    disabled={processing}
                                    placeholder="Brief description of the payment method"
                                    rows={3}
                                    autoComplete="off"
                                />
                                <InputError message={errors.description} />
                            </div>

                            {/* Active Status */}
                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="payment-method-active"
                                    name="is_active"
                                    checked={data.is_active}
                                    onCheckedChange={(checked) => setData('is_active', checked as boolean)}
                                />
                                <Label htmlFor="payment-method-active" className="text-sm font-normal">
                                    Active payment method
                                </Label>
                            </div>

                            {/* Submit Buttons */}
                            <div className="flex gap-4 pt-4">
                                <Button type="submit" disabled={processing}>
                                    {processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                                    Create Payment Method
                                </Button>
                                <Button type="button" variant="outline" onClick={() => window.history.back()}>
                                    Cancel
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>

            {/* Delete Image Confirmation Dialog */}
            <Dialog open={showDeleteImageDialog} onOpenChange={setShowDeleteImageDialog}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Remove Image</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to remove this payment method image? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setShowDeleteImageDialog(false)} disabled={isRemovingImage}>
                            Cancel
                        </Button>
                        <Button variant="destructive" onClick={confirmRemoveImage} disabled={isRemovingImage}>
                            {isRemovingImage && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                            Remove Image
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
