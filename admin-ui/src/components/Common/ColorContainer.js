import React from 'react';

const ColorContainer = ({
                            label,
                            width = "w-1/2",
                            onChange,
                            value = "#FFFFFF",
                            reset_id,
                            handleResetColor,
                            disabled
                        }) => {
    return <div className={`flex flex-col gap-y-1 ${width}`}>
        <p className={`text-light text-xs 2xl:text-sm font-semibold tracking-wider`}>{label}</p>
        <div className={`flex items-center w-full gap-x-2 justify-between`}>
            <div
                className={`border h-11 border-light_border py-2 px-2 rounded-md bg-white flex items-center gap-x-1 2xl:gap-x-2  `}>
                <input
                    type={"color"}
                    className={`rounded-full focus:shadow-none border-none cursor-pointer  h-6 w-6  `}
                    onChange={onChange}
                    value={value}
                />
                <input type={"text"} value={value} onChange={onChange}
                       disabled={disabled}
                       className={`w-1/2 focus:shadow-none  shadow-none border-none`}/>
            </div>
            <div
                title={"reset"}
                id={reset_id}
                className="border  border-light rounded-md h-11 w-10  flex items-center justify-center p-1.5 "
            >
                <i
                    className="text-xl text-primary cursor-pointer leading-0 antialiased p-3 wlr wlrf-refresh  color-important "
                    onClick={() => handleResetColor()}
                />
            </div>
        </div>
    </div>
};
export default ColorContainer;