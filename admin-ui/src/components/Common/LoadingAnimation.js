import React from "react";

const LoadingAnimation = ({height = "h-full"}) => {
    const [text, setText] = React.useState("Loading...");

    React.useEffect(() => {
        let getText = setTimeout(() => {
            setText("If loading takes a while, please refresh the screen...!")
        }, 3000)

        return () => {
            clearInterval(getText)
        }
    }, [])

    return (
        <div className={`bg-white  text-primary space-y-4 flex flex-col items-center justify-center  w-full ${height}`}>
            <i className="wlr wlrf-spinner animate-spin  text-2xl text-primary  "/>
            <p className="text-sm font-medium">{text}</p>
        </div>
    );
};

export default LoadingAnimation;

