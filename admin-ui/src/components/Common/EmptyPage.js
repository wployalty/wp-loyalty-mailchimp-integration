import React from "react";
import { UiLabelContext } from "../../Context";
import Icon from "./Icon";
import Button from "./Button";

const EmptyPage = ({ title, description, buttonText }) => {
    const labels = React.useContext(UiLabelContext);
    const common = labels.common || {};

    const buyProUrl = common.buy_pro_url;

    const handleClick = (e) => {
        e.preventDefault();
        if (buyProUrl) {
            window.open(buyProUrl, "_blank", "noopener,noreferrer");
        }
    };

    const resolvedTitle =
        title || common.premium || common.upgrade_text || "Upgrade to Pro";

    const resolvedDescription =
        description ||
        common.premium_msg ||
        "Activate your license to unlock all Mailchimp integration features.";

    const resolvedButtonText =
        buttonText || common.upgrade_text || resolvedTitle;

    return (
        <div className="w-full h-full flex flex-col items-center justify-center text-center px-6">
            <div className="flex items-center justify-center mb-5">
                <Icon icon="lock" fontSize="text-3xl" fontWeight="font-bold" />
            </div>
            <h2 className="text-xl font-semibold text-dark">
                {resolvedTitle}
            </h2>
            <p className="mt-2 text-sm text-light max-w-md">
                {resolvedDescription}
            </p>
            <div className="mt-5">
                <Button
                    id="wlmi_upgrade_to_pro"
                    bgColor={"bg-white"}
                    textStyle={
                        "text-primary font-medium text-xs  2xl:text-sm_14_l_20"
                    }
                    padding={"xl:px-3 2xl:px-5 px-2.5 py-2.5"}
                    outline={true}
                    outlineStyle={"border-primary"}
                    click={handleClick}
                >
                    {resolvedButtonText}
                </Button>
            </div>
        </div>
    );
};

export default EmptyPage;

