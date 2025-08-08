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

interface PaymentMethod {
    id: number;
    name: string;
    description?: string;
    image_path?: string;
    image_url?: string;
    is_active: boolean;
}

interface EditPaymentMethodProps {
    paymentMethod: PaymentMethod;
}

interface PaymentMethodForm {
    name: string;
    description: string;
    image: File | null;
    is_active: boolean;
    remove_image: boolean;
}

export default function EditPaymentMethod({ paymentMethod }: EditPaymentMethodProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [imagePreview, setImagePreview] = useState<string | null>(paymentMethod.image_url || null);
    const [showDeleteImageDialog, setShowDeleteImageDialog] = useState(false);
    const [isRemovingImage, setIsRemovingImage] = useState(false);
    const [imageError, setImageError] = useState<string | null>(null);
    const [isImageMarkedForDeletion, setIsImageMarkedForDeletion] = useState(false);
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
            title: paymentMethod.name,
            href: `/payment-methods/${paymentMethod.id}`,
        },
        {
            title: 'Edit',
            href: `/payment-methods/${paymentMethod.id}/edit`,
        },
    ];

    const { data, setData, post, put, processing, errors } = useForm<PaymentMethodForm>({
        name: paymentMethod.name || '',
        description: paymentMethod.description || '',
        image: null,
        is_active: paymentMethod.is_active,
        remove_image: false,
    });

    const handleImageChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            setData('image', file);
            setData('remove_image', false); // Reset remove flag when new image is selected
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
        setImageError(null);
        setShowDeleteImageDialog(true);
    };

    const confirmRemoveImage = () => {
        setIsRemovingImage(true);

        // Add a small delay to show the loading state
        setTimeout(() => {
            setData('image', null);
            setData('remove_image', true); // Signal that existing image should be deleted
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
        setData('remove_image', false);
        setIsImageMarkedForDeletion(false);
        setImagePreview(paymentMethod.image_url || null);
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        // Client-side validation
        if (!data.name.trim()) {
            return;
        }

        // Check if we need to use FormData (for file uploads or image deletion)
        const hasImage = data.image !== null;
        const needsFormData = hasImage || data.remove_image;

        if (needsFormData) {
            // Use FormData for file uploads
            const formData = new FormData();

            // Add HTTP method for Laravel (required for PUT with FormData)
            formData.append('_method', 'PUT');

            // Add form fields to FormData, ensuring no null/undefined values
            formData.append('name', data.name || '');
            formData.append('description', data.description || '');
            formData.append('is_active', data.is_active ? '1' : '0');

            // Add image removal flag
            if (data.remove_image) {
                formData.append('remove_image', '1');
            }

            // Add image file to FormData only if it's a valid File object
            if (data.image && data.image instanceof File) {
                formData.append('image', data.image);
            }

            post(`/payment-methods/${paymentMethod.id}`, {
                data: formData,
                forceFormData: true,
                onError: (errors) => {
                    console.error('Payment method update failed:', errors);
                    alert('Failed to update payment method. Please check your input and try again.');
                },
            });
        } else {
            // Use regular form submission when no files
            put(route('payment-methods.update', paymentMethod.id));
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${paymentMethod.name}`} />

            <div className="flex h-full flex-1 flex-col gap-6 p-6">
                <div>
                    <h1 className="text-3xl font-bold">Edit Payment Method</h1>
                    <p className="text-muted-foreground">Update the details of your payment method</p>
                </div>

                <Card className="max-w-2xl">
                    <CardHeader>
                        <CardTitle>Payment Method Details</CardTitle>
                        <CardDescription>Update the details of your payment method</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                            {/* Basic Information */}
                            <div className="space-y-2">
                                <Label htmlFor="edit-payment-method-name">Payment Method Name *</Label>
                                <Input
                                    id="edit-payment-method-name"
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

                            {/* Description */}
                            <div className="space-y-2">
                                <Label htmlFor="edit-payment-method-description">Description</Label>
                                <Textarea
                                    id="edit-payment-method-description"
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

                            {/* Image Upload */}
                            <div className="space-y-2">
                                <Label htmlFor="edit-payment-method-image">Payment Method Image</Label>
                                <div className="space-y-4">
                                    {(imagePreview || paymentMethod.image_url) && !isImageMarkedForDeletion ? (
                                        <div className="relative">
                                            <img
                                                src={imagePreview || paymentMethod.image_url}
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
                                                    <p className="mb-1 text-sm font-medium text-red-600">Image marked for deletion</p>
                                                    <p className="mb-3 text-xs text-red-500">Image will be removed when you save changes</p>
                                                    <Button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={undoImageDeletion}
                                                        className="border-blue-300 text-blue-600 hover:bg-blue-50"
                                                    >
                                                        Undo Deletion
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
                                    <input
                                        ref={fileInputRef}
                                        type="file"
                                        accept="image/*"
                                        onChange={handleImageChange}
                                        className="hidden"
                                        id="edit-payment-method-image"
                                    />
                                </div>
                                <InputError message={errors.image} />
                            </div>

                            {/* Active Status */}
                            <div className="flex items-center space-x-2">
                                <Checkbox
                                    id="edit-payment-method-active"
                                    name="is_active"
                                    checked={data.is_active}
                                    onCheckedChange={(checked) => setData('is_active', checked as boolean)}
                                />
                                <Label htmlFor="edit-payment-method-active" className="text-sm font-normal">
                                    Active payment method
                                </Label>
                            </div>

                            {/* Submit Buttons */}
                            <div className="flex gap-4 pt-4">
                                <Button type="submit" disabled={processing}>
                                    {processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />}
                                    {isImageMarkedForDeletion ? 'Save Changes & Remove Image' : 'Update Payment Method'}
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
                        {imageError && (
                            <div className="mt-2 rounded-md border border-red-200 bg-red-50 p-3">
                                <p className="text-sm text-red-600">{imageError}</p>
                            </div>
                        )}
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
