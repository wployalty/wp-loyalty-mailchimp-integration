import React from 'react';
import Input from "./Input";

const LabelInputContainer = ({
                                 label, width = "w-full", type = "text", onChange, value,
                                 border = "border border-card_border  focus:border-blue_primary  focus:border-1  focus:border-opacity-100",
                                 error_message, error, gap = "gap-y-1", placeHolder
                             }) => {
    return <div className={`flex flex-col ${gap} ${width}`}>
        <p className={`text-light text-xs 2xl:text-sm font-semibold tracking-wider`}>{label}</p>
        <Input
            type={type}
            value={value}
            onChange={onChange}
            border={border}
            error={error}
            placeHolder={placeHolder}
        />
        {error_message && <div className={`flex items-center space-x-1 ${!gap ? "mt-1" : ""}`}>
            <i className="text-md  antialiased wlr wlrf-error font-semibold text-redd color-important "/>
            <p className="text-redd font-semibold text-xs  tracking-wide">{error_message}</p>
        </div>
        }

    </div>
};

export default LabelInputContainer;