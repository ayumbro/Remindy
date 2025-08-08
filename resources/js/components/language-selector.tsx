import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select";
import { useTranslations } from "@/hooks/use-translations";
import { Globe } from "lucide-react";

export function LanguageSelector() {
    const { currentLocale, supportedLocales, changeLocale } = useTranslations();

    return (
        <Select value={currentLocale} onValueChange={changeLocale}>
            <SelectTrigger className="w-[180px]">
                <Globe className="h-4 w-4 mr-2" />
                <SelectValue placeholder="Select language" />
            </SelectTrigger>
            <SelectContent>
                {Object.entries(supportedLocales).map(([code, name]) => (
                    <SelectItem key={code} value={code}>
                        {name}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}